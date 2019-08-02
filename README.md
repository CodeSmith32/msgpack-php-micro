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

### MsgPack::encode($data, $settings = array())
Encodes the data in the msgpack format.

#### `$data`
Any type of data to encode in the msgpack format. Be sure to avoid cyclic references in this object / array structure. In case of a cyclic reference, the stack will overflow, which is a fatal / uncatchable error. If you cannot be sure the data will not have a recursive structure, but you must catch this as an error, use the `'testrecursion'` setting.

#### `array $settings`
An array of settings used for encoding. For compatibility, if a boolean is provided instead, it affects the value of the `'stringbuffers'` setting, and all other settings use their defaults.

**`'stringbuffers' => boolean`**

If strings should be encoded in the msgpack binary type versus the msgpack string type. If this setting is used, all strings occurring in the object structure will be encoded as the binary type. Otherwise strings will be encoded in the string type.

Default: `FALSE`

**`'testrecursion' => boolean`**

If the data passed in has a recursive structure, the encoding process will follow it recursively, and ultimately trigger a fatal stack overflow error, which cannot be caught with a `try {} catch {}`. If it is unknown whether the data is recursively structured or not, then enabling this parameter will first test the data to see if it is recursively structured. If it is, a catchable error will be thrown before attempting to encode it, instead of running into a stack overflow.

Try to avoid this setting when possible, as it will add significant overhead for large object structures. The process for testing for recursive structure is explained by [this stackoverflow.com answer](https://stackoverflow.com/questions/9042142/detecting-infinite-array-recursion-in-php#9042169).

Default: `FALSE`

#### Return: `string`
Returns the data encoded in the msgpack format as a PHP string.

----------------------------------------------------------------

### MsgPack::decode($buffer, $settings = array())
Decodes the data from the msgpack format into a PHP data structure.

#### `string $buffer`
The msgpack-formatted buffer to decode, as a string.

#### `array $settings`
An array of settings used for decoding. For compatibility, if a boolean is provided instead, it affects the value of the `'associative'` setting, and all other settings use their defaults.

**`'associative' => boolean`**

If msgpack map types should be decoded as associative arrays instead of objects.

- If this setting is `FALSE`, occurrences of the msgpack map / object type are decoded as objects (instances of PHP's `stdClass`).
- If this setting is `TRUE`, map / object type occurrences are decoded as associative arrays.

Default: `FALSE`

#### Return: `any`
Returns the PHP structure decoded from the provided msgpack data. It could be `NULL`, `TRUE`, `FALSE`, a string, an object instance of `stdClass` with further mapping, or an array with further mapping.

----------------------------------------------------------------

### MsgPack::extend($extension)
Adds an extension type to the msgpack decoder.

#### `array $extension`
The extension parameters. This array contains the following properties, specifying how the extension integrates:

**`'type' => int` (required)**

The msgpack extension code (0 to 127). This code is used as the data code for the msgpack extension type. Trying to register multiple extensions under the same code will trigger an exception.

**`'varType' => string`**

The type of the PHP values that will be passed through this extension for encoding. This parameter should match one of the values returned by PHP's [`gettype()`](https://www.php.net/manual/en/function.gettype.php) function. While iterating over the PHP's data structure, if this type of value is encountered, it will be passed to the extension, and the extension can determine to either encode it or reject it.

Default: `'object'`

**`'encode' => callable` (required)**

The callback triggered while data is being encoded. This callback acts as both a filter and an encoder. As being of callable type, the callback may be an anonymous function, the function name as a string, or an array (see PHP's [Callbacks / Callables](https://www.php.net/manual/en/language.types.callable.php) for details). The callback follows the form,

`function encode(any $object) -> string | boolean`

When an object is encountered during the encoding iteration (for values of another type, see the `'varType'` parameter), it is passed to the first extension registered for objects, by triggering the extension's `encode` callback, passing this object value. The extension may then choose to either accept or reject it.

If the extension's `encode` callback returns a string, the extension accepts it, and the string is used as the data to encode the object in the extension's format.

But, if the extension's `encode` callback returns `FALSE`, the extension rejects it. In this case, the value is then passed to the next extension in the same way. If all extensions reject a value for a specific type (or if no extensions are registered for the type), the value is encoded normally.

Extensions are tested in the order they are registered. The first extension to accept the value is used, even if an extension registered later would also accept it.

**`'decode' => callable` (required)**

The callback triggered for data encoded with the extension code this extension is registered for. This callback follows the form,

`function decode(string $buffer) -> any`

When the msgpack decoder encounters a msgpack item of type 'extension', the extension code is looked up in the list of registered extensions. If the extension is not found, `NULL` is returned instead and the extension's buffer is skipped. If, however, an extension is registered for the queried extension code, the extension's `decode` callback is triggered and passed the sub-buffer selected by the extension type, as a string. This buffer does not include any of msgpack's 'extension' type header data. Whatever value this callback returns is mapped into the fully decoded data structure.

#### Return: `NULL`
This function doesn't return anything.

----------------------------------------------------------------

## TODO:
- The Timestamp extension type
- Thoroughly test
