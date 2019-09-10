# msgpack-php-micro
msgpack - A super-lightweight PHP implementation of the [msgpack](http://msgpack.org/) data encoding format.

This is a very simple, small PHP implementation of msgpack, for ease of embedding.

Notice: This implementation is still fairly new, and may have bugs. If you find any bugs, please report them immediately, and I will try to get them fixed as soon as possible.

**Usage:**

```
require_once('msgpack.php');

$data = array(
  'hello' => 'world',
  'array' => array(1, 2, 3, 4),
  5 => 78.662,
  'dt' => new DateTime(), // DateTime encoded in Timestamp extension format
);

$encoded = MsgPack::encode($data);

$decoded = MsgPack::decode($encoded);

var_dump($decoded);
```

Fully supports custom type extensions:
```
require_once('msgpack.php');

class Vertex {
	public $x;
	public $y;
	public $z;

	public function __construct($x=0, $y=0, $z=0) {
		$this->x = floatval($x);
		$this->y = floatval($y);
		$this->z = floatval($z);
	}
}

MsgPack::extend(array(
	'type' => 1,

	'varType' => 'object',

	'encode' => function($obj) {
		// returning FALSE skips; see reference for details
		if(!($obj instanceof Vertex)) return FALSE;

		return pack('GGG',$obj->x, $obj->y, $obj->z);
	},

	'decode' => function($data) {
		$xyz = unpack('Gx/Gy/Gz',$data);
		return new Vertex($xyz['x'], $xyz['y'], $xyz['z']);
	},
));
```

Visit [github.com/CodeSmith32/msgpack-php-micro](https://github.com/CodeSmith32/msgpack-php-micro) for the usage reference.