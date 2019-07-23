<?php

if(!class_exists('MsgPack')):
class MsgPack {
	// static-only class
	private function __construct() {}

	private static $extensions = array();
	private static $extensionCodes = array();

	private static $strsAsBufs = NULL;
	private static $assocArrays = NULL;
	private static $buffer = NULL;
	private static $pos = 0;

	private static $i16 = 'n';
	private static $i32 = 'N';
	private static $i64 = 'J';
	private static $f32 = 'G';
	private static $f64 = 'E';

	private static function needsF64($n) {
		$n = abs($n);
		$lg = log($n,2);
		if($lg < -120 || $lg > 120) return true;

		$n = $n / pow(2,floor($lg) - 23);
		return $n !== floor($n);
	}

	private static function isArray($arr) {
		$l = count($arr);
		for($i=0;$i<$l;$i++)
			if(!isset($arr[$i])) return FALSE;
		return TRUE;
	}

	private static function read($l) {
		if(self::$pos + $l > strlen(self::$buffer))
			throw new Exception('MsgPack Error: Unexpected end of data');

		$str = substr(self::$buffer,self::$pos,$l);
		self::$pos += $l;
		return $str;
	}
	private static function ui16() {
		$d = unpack(self::$i16.'n',self::read(2));
		return $d['n'];
	}
	private static function ui32() {
		$d = unpack(self::$i32.'n',self::read(4));
		return $d['n'];
	}
	private static function ui64() {
		$d = unpack(self::$i64.'n',self::read(8));
		return $d['n'];
	}
	private static function i8() {
		$v = ord(self::read(1));
		if($v & 0x80) $v |= -1^255;
		return $v;
	}
	private static function i16() {
		$v = self::ui16();
		if($v & 0x8000) $v |= -1^255;
		return $v;
	}
	private static function i32() {
		$v = self::ui32();
		return $v >> 0;
	}
	private static function i64() {
		$hi = self::ui32();
		$lo = self::ui32();
		$v = $hi * 0x100000000;
		if($hi&0x800000000)
			$v -= $lo;
		else
			$v += $lo;
		return $v;
	}
	private static function f32() {
		$d = unpack(self::$f32.'n',self::read(4));
		return $d['n'];
	}
	private static function f64() {
		$d = unpack(self::$f64.'n',self::read(8));
		return $d['n'];
	}

	private static function dec_obj($l) {
		if(self::$assocArrays) {
			$o = array();
			for($i=0;$i<$l;$i++)
				$o[self::dec()] = self::dec();
			return $o;
		}
		$o = new stdClass();
		for($i=0;$i<$l;$i++) {
			$prop = self::dec();
			$o->$prop = self::dec();
		}
		return $o;
	}
	private static function dec_arr($l) {
		$o = array();
		for($i=0;$i<$l;$i++)
			$o []= self::dec();
		return $o;
	}
	private static function dec_ext($ty,$buf) {
		if(!isset(self::$extensionCodes[$ty]))
			return NULL;
		return call_user_func(self::$extensionCodes[$ty]['dec'],$buf);
	}

	private static function enc($obj) {
		$ty = gettype($obj);
		if(isset(self::$extensions[$ty])) {
			foreach(self::$extensions[$ty] as $ext) {
				$buf = call_user_func($ext['enc'],$obj); // call extension encode
				if($buf === FALSE) continue; // returning FALSE passes it on
				if(!is_string($buf))
					throw new Exception("MsgPack Error: Extension code {$ext['ty']} failed to return a string");

				$ret = '';
				$l = strlen($buf);
				if($l === 1)
					$ret .= "\xd4";
				elseif($l === 2)
					$ret .= "\xd5";
				elseif($l === 4)
					$ret .= "\xd6";
				elseif($l === 8)
					$ret .= "\xd7";
				elseif($l === 16)
					$ret .= "\xd8";
				elseif($l < 256)
					$ret .= "\xc7" . chr($l);
				elseif($l < 65536)
					$ret .= "\xc8" . pack(self::$i16, $l);
				else
					$ret .= "\xc9" . pack(self::$i32, $l);

				$ret .= chr($ext['ty']) . $buf;
				return $ret;
			}
		}
		switch($ty) {
			case 'NULL':
				return "\xc0";
			case 'boolean':
				return $obj ? "\xc2" : "\xc3";
			case 'integer':
				// signed
				if($obj < 0) {
					if($obj >= -32)
						return chr($obj);
					if($obj >= -128)
						return "\xd0" . chr($obj);
					if($obj >= -32768)
						return "\xd1" . pack(self::$i16, $obj);
					if($obj >= -0x100000000)
						return "\xd2" . pack(self::$i32, $obj);
					return "\xd3" . pack(self::$i64, $obj);
				}

				// unsigned
				if($obj < 128)
					return chr($obj);
				if($obj < 256)
					return "\xcc" . chr($obj);
				if($obj < 65536)
					return "\xcd" . pack(self::$i16, $obj);
				if($obj < 0x100000000)
					return "\xce" . pack(self::$i32, $obj);
				return "\xcf" . pack(self::$i64, $obj);
			case 'double':
				if(self::needsF64($obj))
					return "\xcb" . pack(self::$f64, $obj);
				return "\xca" . pack(self::$f32, $obj);
			case 'string': // treat as buffers
				if(self::$strsAsBufs) {
					$l = strlen($obj);
					if($l < 256)
						return chr(0xc4) . chr($l) . $obj;
					if($l < 65536)
						return chr(0xc5) . pack(self::$i16, $l) . $obj;
					return chr(0xc6) . pack(self::$i32, $l) . $obj;
				}
				$l = strlen($obj);
				if($l < 32)
					return chr(0xa0 | $l) . $obj;
				if($l < 256)
					return chr(0xd9) . chr($l) . $obj;
				if($l < 65536)
					return chr(0xda) . pack(self::$i16, $l) . $obj;
				return chr(0xdb) . pack(self::$i32, $l) . $obj;
			case 'array':
				if(self::isArray($obj)) {
					$l = count($obj);
					$buf = '';
					if($l < 16)
						$buf .= chr(0x90 | $l);
					elseif($l < 65536)
						$buf .= "\xdc" . pack(self::$i16, $l);
					else
						$buf .= "\xdd" . pack(self::$i32, $l);

					for($i=0;$i<$l;$i++)
						$buf .= self::enc($obj[$i]);
					return $buf;
				}
				// associative arrays fall through
			case 'object':
				$l = 0;
				$buf = '';
				foreach($obj as $v) $l++;

				if($l < 16)
					$buf .= chr(0x80 | $l);
				elseif($l < 65536)
					$buf .= "\xde" . pack(self::$i16, $l);
				else
					$buf .= "\xdf" . pack(self::$i32, $l);

				foreach($obj as $k => $v)
					$buf .= self::enc($k) . self::enc($v);
				return $buf;
		}
		throw new Exception('MsgPack Error: Could not encode value of type '.$ty);
	}

	private static function dec() {
		$b = ord(self::read(1));
		if(($b&0x80) === 0) return $b; // +fixint
		if(($b&0xe0) === 0xe0) return $b|(-1^255); // -fixint
		if(($b&0xf0) === 0x80) return self::dec_obj($b&15); // fixmap
		if(($b&0xf0) === 0x90) return self::dec_arr($b&15); // fixarray
		if(($b&0xe0) === 0xa0) return self::read($b&31); // fixstr
		switch($b) {
			case 0xc1: // ehh.. just map it to nil
			case 0xc0: return NULL; // nil
			case 0xc2: return FALSE; // false
			case 0xc3: return TRUE; // true
			case 0xd9: case 0xc4: return self::read(ord(self::read(1))); // bin 8 / str 8
			case 0xda: case 0xc5: return self::read(self::ui16()); // bin 16 / str 16
			case 0xdb: case 0xc6: return self::read(self::ui32()); // bin 32 / str 32
			case 0xc7: $l = ord(self::read(1)); return self::dec_ext(ord(self::read(1)),self::read($l)); // ext 8
			case 0xc8: $l = self::ui16(); return self::dec_ext(ord(self::read(1)),self::read($l)); // ext 16
			case 0xc9: $l = self::ui32(); return self::dec_ext(ord(self::read(1)),self::read($l)); // ext 32
			case 0xca: return self::f32(); // float 32
			case 0xcb: return self::f64(); // float 64
			case 0xcc: return ord(self::read(1)); // uint 8
			case 0xcd: return self::ui16(); // uint 16
			case 0xce: return self::ui32(); // uint 32
			case 0xcf: return self::ui64(); // uint 64
			case 0xd0: return ord(self::read(1)); // int 8
			case 0xd1: return self::i16(); // int 16
			case 0xd2: return self::i32(); // int 32
			case 0xd3: return self::i64(); // int 64
			case 0xd4: return self::dec_ext(ord(self::read(1)),self::read(1)); // fixext 1
			case 0xd5: return self::dec_ext(ord(self::read(1)),self::read(2)); // fixext 2
			case 0xd6: return self::dec_ext(ord(self::read(1)),self::read(4)); // fixext 4
			case 0xd7: return self::dec_ext(ord(self::read(1)),self::read(8)); // fixext 8
			case 0xd8: return self::dec_ext(ord(self::read(1)),self::read(16)); // fixext 16
			case 0xdc: return self::dec_arr(self::ui16()); // array 16
			case 0xdd: return self::dec_arr(self::ui32()); // array 32
			case 0xde: return self::dec_obj(self::ui16()); // map 16
			case 0xdf: return self::dec_obj(self::ui32()); // map 32
		}
		throw new Error("MsgPack Error: Somehow encountered unknown byte code: "+b);
	}

	public static function encode($obj,$settings=array()) {
		self::$strsAsBufs = isset($settings['stringbuffers']) ? $settings['stringbuffers'] : FALSE;

		if($settings === TRUE) self::$strsAsBufs = TRUE;
		
		$encoded = self::enc($obj);

		return $encoded;
	}

	public static function decode($str,$settings=array()) {
		self::$assocArrays = isset($settings['associative']) ? $settings['associative'] : FALSE;

		if($settings === TRUE) self::$assocArrays = TRUE;

		self::$buffer = $str;
		$decoded = self::dec();
		self::$buffer = NULL;

		return $decoded;
	}

	public static function extend($ext) {
		$type = $ext['type'];
		$typeof = isset($ext['varType']) ? $ext['varType'] : 'object';
		$encode = $ext['encode'];
		$decode = $ext['decode'];

		if(!is_callable($encode))
			throw new Exception("MsgPack Error: Extension for code $type must have callable 'encode'");
		if(!is_callable($decode))
			throw new Exception("MsgPack Error: Extension for code $type must have callable 'decode'");
		if(isset(self::$extensionCodes[$type]))
			throw new Exception("MsgPack Error: Trying to register extension code $type more than once");

		$ext = array(
			'ty' => $type,
			'enc' => $encode,
			'dec' => $decode,
		);

		if(!isset(self::$extensions[$typeof]))
			self::$extensions[$typeof] = array();
		self::$extensions[$typeof] []= $ext;
	}
}
endif;

?>