# msgpack-php-micro
msgpack - A super-lightweight PHP implementation of the [msgpack](http://msgpack.org/) data encoding format.

This is an extremely small PHP implementation of msgpack, for ease of embedding.

**Usage:**

```
require_once('msgpack.php');

$data = array(
  'hello' => 'world',
  'array' => array(1, 2, 3, 4),
  5 => 78.662,
);

$encoded = MsgPack::encode($data);

$decoded = MsgPack::decode($encoded);

var_dump($decoded);
```

## General Reference

```
// Encode data in the msgpack format
// $object: The object to encode
// $stringsAsBins: If strings should be encoded in the msgpack binary type
MsgPack::encode(any $object, boolean $stringsAsBins = FALSE) -> string

// Decode data from the msgpack format
// $data: The msgpack string data
// $associativeArrays: If objects should be decoded as associative arrays;
//   otherwise they are decoded as instances of stdClass
MsgPack::decode(string $data, boolean $associativeArrays = FALSE) -> any;

// Add an extension
// $extensionArray: An associative array mapping the following properties:
// array(
//  'type' => int,         The msgpack extension code (0 to 127)
//  'encode' => callable,  The encode callback. This callback follows the form
//                           encode(any $object) -> string | boolean;
//                         If this callback returns FALSE, the extension rejects
//                         the object, and it's encoded normally. If this callback
//                         returns a string, the string is used as the buffer to
//                         encode the object in an extension format.
//  'decode' => callable,  The decode callback. This callback follows the form
//                           decode(string $data) -> any;
//                         The callback accepts the data string and is responsible
//                         to decode, build, and return the object.
//  'varType' => string,   The type of objects fed to the extension.
//                         Values matching this type (when tested with gettype())
//                         are tested through this extension's encode() before
//                         encoding normally. This parameter is optional, and
//                         defaults to 'object'.
MsgPack::extend(Array $extensionArray) -> void;

// Note, extensions are loaded on a first-in priority basis: The first extension added
// for a given varType is called before testing the next registered extension with the
// same varType.
```

## TODO:
- The Timestamp extension type.
- Thoroughly test
