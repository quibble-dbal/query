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

To create a Query Builder, call one of the static methods the trait adds. Their
only parameter is the base table name you want to query from:

```php
<?php

$query = $adapter::selectFrom('foo');

```

You can now pass the `$query` around, call methods to add stuff like conditions,
options, joins etc. and eventually fetch the result(s). Most calls will return
the object itself so you can chain calls.

You can also directly instantiate one of the `Query` SQL classes, e.g.:

```php
<?php

$query = new Quibble\Query\Select($pdo, $tableName);
```

## Joining
Low-level:

```php
<?php

$query->join('bar USING(baz)', 'LEFT');

```

The second parameter is the join-style. If omitted defaults to a straight join.
If you join contains placeholders, add them as subsequent parameters.

Shorthands:

```php
<?php

$query->leftJoin('bar1 USING(baz1)')
    ->rightJoin('bar2 USING(baz2)')
    ->outerJoin('bar3 USING(baz3)')
    ->fullOuterJoin('bar4 ON baz4 = ?', $baz4);

```

Again, any subsequent parameters are bindings to placeholders.

## Choosing which fields to select
The default is to select `*` so if that's what you want you don't need to
specify anything. To fine-tune, use the `select` method:

```php
<?php

$query->select('foo', ['bar', 'baz AS buzz']);

```

`select` takes an arbitrary number of arguments which may be either strings
or arrays of strings. Hence, the above example would translate to:

```sql
SELECT foo, bar, baz AS buzz FROM ...
```

Note that if `select` is called multiple times, all fields are _appended_ to
the query. The only exception if when it detects the fields were in a "pristine"
state (i.e. `*`) in which case it acts as an override.

## Adding WHERE clauses
```php
<?php

$query->where('foo = ?', $bar);

```

A WHERE clause is an AND clause by default. To add an OR clause, use `orWhere`:

```php
<?php

$query->orWhere('foo = ?', $bar);

```

WHERE clauses are wrapped in parentheses and appended verbatim. Hence, to nest
calls one would simply pass in multiple clauses in parantheses where applicable:

```php
<?php

$query->where('foo = ? AND (bar = ? OR bar = ?)', $foo, $bar1, $bar2);
// ... (AND) foo = ? AND (bar = ? OR bar = ?)

```

`where` and `orWhere` take as many arguments as is needed to build the clause.
Note that it does not check for validity; supplying the correct number of
arguments is up to the programmer. You can also use `:paramName` style argument
injection. In that case, pass the arguments as a key/value hash with
corresponding key names (this may be a single hash or a hash-per-parameter):

```php
<?php

$query->where('foo = :foo AND bar = :bar', compact('foo', 'bar'));

```

## Ordering
Use the `order` method:

```php
<?php

$query->order('foo ASC');

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

$query->limit(10, 5);
// LIMIT 10 OFFSET 5

```

## Unions
The `union` method accepts _another_ `Select` object to use for the union. It
will show default error handling (see below) if the selected fields are
incompatible, so that's up to you.

```php
<?php

$query->union($anotherQuery);

```

The second argument `$unionStyle` is optional and specifies the union style to
use (`DISTINCT` or `ALL` or whatever your engine supports). It defaults to
`DISTINCT` (like SQL).

There is also a shorthand `unionAll` method which does what you'd expect.

## IN statements
The SQL IN operator is very handy to filter results based on other results.
Using a regular `where` call you would just write out the subquery and bind
the necessary parameters, e.g.:

```php
<?php

$query->where('id IN (SELECT foo_id FROM bar WHERE bar_id = ?)', $bar_id);
```

However, sometimes your set comes from another source and you need to manually
inject them (and the bindings) into your statement. The `Select` query builder
offers a convenience method for this:

```php
<?php

$query->where($query->in('field', $arrayOfPossibleValues));

```

Similarly, there's a `notIn` method. These methods return _strings_ so you can
directly pass them to `where`. The bindings are automatically added to the query
object, so generally you'll use them in conjunction as in the above example.

## GROUPing queries
A `Select` query can have a single `GROUP BY` clause added (i.e., if you call it
multiple times it will be overwritten). Use the `groupBy` method:

```php
<?php

$query->groupBy('foo, bar');

```

In conjunction, there is also a `having` method which accepts bindable
additional parameters:

```php
<?php

$query->groupBy('foo, bar')
    ->having('bar > ?', 42);

```

## Fetching the data
Eventually, you're done building your query and will want data. Just use any
or the `fetch*` methods as you would on a `PDOStatement`. They accept the same
arguments and proxy to the underlying statement.

Quibble\Query also supports a `generate` method which returns a generator
instead (handy for large query results). It's parameters are the same as `fetch`
and are passed verbatim:

```php
<?php

$results = $query->generate(PDO::FETCH_ASSOC);
foreach ($result as $results) {
    // ...
}
```

## Counting rows
The `Select` builder also offers a simple convenience method to simply count the
number of results. You've guessed it, it's called `count` :) It takes a single
argument, `$count`, specifying what to count. It defaults to `"*"` so most of
the times you can omit it.

This method returns an integer with the number of found rows.

## Inserting data
Use the `Insert` class. It's `execute` method accepts a key/value pair of values
and performs the insert immediately:

```php
<?php

$result = (new Insert($pdo, 'tablename'))->execute(['foo' => 'bar']);
// INSERT INTO tablename (foo) VALUES (?) with bound parameter 'bar'
```

You can pass multiple arrays which will result in multiple inserts:

```php
<?php

// Insert up to n rows:
$query->execute($array1, $array2, $arrayn);
```

Adapters implementing the `Bindable` trait have the convenienst method
`insertInto` defined:

```php
<?php

$query = $pdo::insertInto('fooTable');
```

> When passing multiple arrays, mutliple `INSERT` statements are executed.
> The `execute` method will only return true if _all_ statements succeed. When
> using the Exception error mode, it will throw an exception if _any_ of the
> statements fail, but the others _will_ succeed. If that is not what you want,
> wrap the call in a transaction and roll back if you catch an exception or it
> returns false.

## Updating data
Updating works like inserting, only the builder only accepts one set of
key/value pairs to update. Like `Select` it uses the `Where` trait so you can
control what gets updated:

```php
<?php

$query = new Quibble\Query\Update($pdo, $tableName);
// or, alternatively:
$query = $pdo::updateTable($tableName);

$query->where('foo = ? AND bar = ?', $foo, $bar)
    ->execute(['baz' => $foobar]);
// UPDATE tableName SET baz = ? WHERE foo = ? AND bar = ?
// with bindings foo, bar and foobar.
```

## Deleting data
Like updating, only the `execute` method does not accept any parameters:
```php
<?php

$query = new Quibble\Query\Delete($pdo, $tableName);
// or, alternatively:
$query = $pdo::deleteFrom($tableName);

$query->where('foo = ? AND bar = ?', $foo, $bar)
    ->execute();
// DELETE FROM tableName WHERE WHERE foo = ? AND bar = ?
// with bindings foo and bar.
```

## Error modes
Quibble\Query respects the `ATTR_ERRMODE` setting of the adapter used to
instantiate each builder, augmenting it with its own more specific exceptions
(these each are instances of `PDOException` as well).

Overriding the ERRMODE on specific statements is not supported and may cause
unexpected results as the Query classes consistently look towards the statement
to determine this setting. Having said that, in 20+ years of programming I've
personally never felt the need to override this on a per-query basis.

## Decorating fields
Any field passed as a binding for any statement type may be decorated in a
class. The only prerequisite is that this class has to `__toString()` method
which renders the field suitable for usage in SQL. E.g., for date fields one
could do this:

```php
<?php

$result = $pdo::insertInto('foo', ['date' => new Carbon\Carbon('+1 day')]);
```

For the converse (during selects), call the `addDecorator` method on the
`Select` builder. It takes two arguments: the name of the field, and the
classname to decorate with. It is assumed that the first parameter to its
constructor will be the value; any additional arguments are passed as
constructor arguments. Example:

```php
<?php

$query = $pdo::selectFrom('foo')
    // bar contains a date:
    ->addDecorator('bar', Carbon\Carbon::class);

$result = $query->fetch();
get_class($result['bar']); // Carbon\Carbon

```

The Quibble\Dabble package contains a generic decorator `Raw` allowing you to
pass in arbitray SQL without any escaping. If you for whatever reason have a
custom decorator you need to inject verbatim, either have it extend `Raw` or
simply wrap it in one:

```php
<?php

use Quibble\Dabble\Raw;

class MyDecorator
{
    private $value = '';

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value.'()';
    }
}

$pdo::insertInto('foo', ['bar' => new Raw(new MyDecorator('bar'))]);
// Results in:
// INSERT INTO foo (bar) VALUES (bar());
// (Obviously this would only work if you actually have a function named bar()
// in your database.)

```

Note that `addDecorator` can be chained like all other methods.

Instead of a fieldname/classname pair, you can also pass a _callable_ as a
single argument. This will be called for every field used with the fieldname
and -value as its arguments. This allows you to dynamically decorate fields that
e.g. "quack like a duck" or contain certain characters in their name that you
know are "special markers" in your database schema (e.g. the string `date`).

The callable should return the value (be it either modified or not).

A third way to call `addDecorator` is with fieldname/callable arguments. For the
specified fieldname, the value will simply be run through the callable. It
expects a single argument (the value) and should return the modified value.
E.g.:

```php
<?php

$query->addDecorator('boolean_field', function ($value) {
    return (bool)$value;
});

```

## Accessing the raw statement
All Query classes offer a `getStatement()` method which returns the prepared
statement. This could come in useful if you need to do something really evil,
or simply if you need to pass `$driver_options` to `prepare`. The options are
the parameter to `getStatement` and are passed verbatim (the SQL is injected for
you).

Note that modifying the Query object after calling `getStatement` obviously
won't modify its previous return value.

Additionally, the Query classes also provide a `getExecutedStatement` which
returns the current statement after being executed with the current bindings.
This would allow you to call more low-level methods on an already executed
statement (e.g. `getColumnMeta`).

## Creating custom query builders
Extending query classes and/or using traits, you can of course build your own
project-specific query builders! E.g., if you need to generically decorate any
field with `date` in it to a `Carbon` object:

```php
<?php

class MySelect extends Quibble\Query\Select
{
    public function __construct(PDO $pdo, $table)
    {
        parent::__construct($pdo, $table);
        $this->addDecorator(function ($field, $value) {
            if (strpos($field, 'date') !== false) {
                $value = new Carbon\Carbon($value);
            }
            return $value;
        });
    }
}

```

