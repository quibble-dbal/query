# Quibble\Query
Query builder for Quibble adapters.

## Usage
Create a custom class extending your desired adapter which uses the
`Quibble\Query\Buildable` trait:

```php
<?php

// PHP 5.x
class MyAdapter extends Quibble\Postgresql\Adapter
{
    use Quibble\Query\Buildable;
}

$adapter = new MyAdapter(/* connection params */);

// PHP 7
$adapter = new class(/* connection params */) extends Quibble\Postgresql\Adapter
{
    use Quibble\Query\Buildable;
};

```

To create a Query Builder, call the static `from` method the trait adds. Its
only parameter is the base table name you want to query from:

```php
<?php

$query = $adapter::from('foo');

```

You can now pass the `$query` around, call methods to add stuff like conditions,
options, joins etc. and eventually fetch the result(s). Every call chains to a
new instance of the query builder with the previous settings attached.

## Joining
Low-level:

```php
<?php

$query = $query->join('bar USING(baz)', 'LEFT');

```

The second parameter is the join-style. If omitted defaults to a straight join.
If you join contains placeholders, add them as subsequent parameters.

Shorthands:

```php
<?php

$query = $query->leftJoin('bar1 USING(baz1)')
    ->rightJoin('bar2 USING(baz2)')
    ->outerJoin('bar3 USING(baz3)')
    ->fullOuterJoin('bar4 UsING(baz4)');

```

Again, any subsequent parameters are bindings to placeholders.

## Choosing which fields to select
The default is to select `*` so if that's what you want you don't need to
specify anything. To fine-tune, use the `select` method:

```php
<?php

$query = $query->select('foo', ['bar', 'baz AS buzz']);

```

`select` takes an arbitrary number of arguments which may be either strings
or arrays of strings. Hence, the above example would translate to:

```sql
SELECT foo, bar, baz AS buzz FROM ...
```

Note that if `select` is called multiple times, all fields are _appended_ to
the query.

## Adding WHERE clauses
```php
<?php

$query = $query->where('foo = ?', $bar);

```

A WHERE clause is an AND clause by default. To add an OR clause, use `orWhere`:

```php
<?php

$query = $query->orWhere('foo = ?', $bar);

```

WHERE clauses are wrapped in parentheses and appended verbatim. Hence, to nest
calls one would simply pass in multiple clauses in parantheses where applicable:

```php
<?php

$query = $query->where('foo = ? AND (bar = ? OR bar = ?)', $foo, $bar1, $bar2);
// ... (AND) foo = ? AND (bar = ? OR bar = ?)

```

`where` and `orWhere` take as many arguments as is needed to build the clause.
Note that it does not check for validity; supplying the correct number of
arguments is up to the programmer. You can also use `:paramName` style argument
injection. In that case, pass the arguments as a key/value hash with
corresponding key names (this may be a single hash or a hash-per-parameter):

```php
<?php

$query = $query->where('foo = :foo AND bar = :bar', compact('foo', 'bar'));

```

## Ordering
Use the `order` method:

```php
<?php

$query = $query->order('foo ASC');

```

Once again, you can pass multiple arguments, concatenate them yourself or call
the method multiple times.

> At this point it should be noted that, apart from the final fetch call, all
> methods can be called in any order (with the caveat that of course the order
> multiple `where`/`order` calls will influence the resulting query).

## Limiting
Use the `limit` method. The first argument is the number to limit to, the
optional second one is the offset. Technically the first argument is also
optional - if you pass null and a number as the second argument you get
just the offset.

```php
<?php

$query = $query->limit(10, 5);
// LIMIT 10 OFFSET 5

```

## Unions
The `union` method accepts _another_ query builder object to use for the union.
It throws a `PDOException` if the `select`ed fields differ.

```php
<?php

$query = $query->union($anotherQuery);

```

## Fetching the data
Eventually, you're done building your query and will want data. Just use any
or the `fetch*` methods as you would on a `PDOStatement`. They accept the same
arguments and proxy to the underlying statement.

## Error modes
By default, fetching dats works as in PDO itself: if nothing is found, `false`
is returned. To instead use exceptions like Dabble adapters do when using the
convenience methods, call `setErrorMode` on the query object:

```php
<?php

$query->setErrorMode(Quibble\Query\ERRMODE_EXCEPTION);
// or ERRMODE_DEFAULT for the default behaviour

```

You can also call `setErrorMode` on an adapter using the `Buildable` trait to
set this for _all_ query objects created subsequently.

