# Quibble\Query
Query builder for Quibble adapters.

## Usage
Create a custom class extending your desired adapter which uses the
`Quibble\Query\Buildable` trait:

```php
<?php

$adapter = new class(/* connection params */) extends Quibble\Postgresql\Adapter
{
    use Quibble\Query\Buildable;
};

```

To create a Query Builder, call one of the convenience methods the trait adds.
Their only parameter is the base table name you want your query to operate on:

```php
<?php

$query = $adapter->select('foo');

```

You can now pass the `$query` around, call methods to add stuff like conditions,
options, joins etc. and eventually fetch the result(s). Most calls will return
the object itself so you can chain calls. *Note:* for these chainable methods
(generally on SELECT queries), the query is _cloned_. So, if you need to work
with a query object within an `if {...}` condition, say, make sure you assign
the result. E.g.:

```php
<?php

$query = $adapter->select('foo');
if ($someCondition) {
    $query = $query->where('bar = ?', $baz);
}
var_dump($query->fetchAll());
```

You can also directly instantiate one of the `Query` SQL classes, e.g.:

```php
<?php

$query = new Quibble\Query\Select($pdo, $tableName);
```

Usually `$tableName` will be a string, but for the `Select` object, it can
actually be _another_ `Select` object, so it becomes possible to construct
endlessly complex (sub)queries:

```php
<?php

$query = $adapter->select($adapter->select('foo')->where('1=1'));
```

## Selecting from more than one table
Just specify multiple table names:

```php
<?php

$query = $adapter->select('foo, bar');
// SELECT * FROM foo, bar

```

## Joining
As of `quibble/query` v2, joining is done via a _callback_ receiving a `Join`
object as its single parameter:

```php
<?php

use Quibble\Query\Join;

$query = $query->join(function (Join $join) {
    $join->inner('bar')
        ->on('bar.baz = foo.baz');
});

```

The `Join` class supports the following methods for the various join styles,
each of which expects the desired table as its parameter:

- `inner` - a normal, straight JOIN
- `left` - a LEFT JOIN
- `right` - a RIGHT JOIN
- `full` - a FULL JOIN

Not that `$table` may be either a string, or a `Select` object itself. The
builder makes sure all bindings are resolved correctly.

> The Query builder does not support, anywhere you can pass bindings for
> placeholders, the use of named parameters. _Always_ use question marks. Named
> parameters are handy when manually constructing a large blob of SQL with many
> of them, but using the query builder you typically only pass one or two
> bindings per method call so it is not needed to support this.

## Subqueries
If any bindable or joined table is itself a `Select` query builder, it becomes
a _subquery_ and the associated bindings are "hoisted" to the parent query.

You can also specifically instruct a built query to act as a subquery with an
optional alias:

```php
<?php

$query = $db->select('foo')->fields('bar');

$query = $query->asSubquery('foobar');

echo $db->select('baz')->fields($query);
// "SELECT (SELECT bar FROM foo) foobar FROM baz"
```

## Choosing which fields to select
The default is to select `*` so if that's what you want you don't need to
specify anything. To fine-tune, use the `fields` method:

```php
<?php

$query = $query->fields('foo', 'bar', 'baz AS buzz');

```

`fields` takes an arbitrary number of arguments representing the fields you wish
to select in your query. Hence, the above example would translate to:

```sql
SELECT foo, bar, baz AS buzz FROM ...
```

Note that if `fields` is called multiple times, only the last call specifies the
fields to select.

## Decorating fields
A decorator class is anything that wraps a value and can be `__toString`'d. For
bindings this is done automatically.

To automatically wrap a _selected_ field in a decorator, take a look at the
`quibble/transformer` package.

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
arguments is up to the programmer.

## Grouping complex WHEREs
If your WHERE clause gets really complicated, you can also pass a callable to
any of the `where` functions instead of a string of SQL. This will be invoked
with a single argument of the type `Quibble\Query\Group`. This object implements
a subset of the query builder (to be precise, the methods of the `Where` trait)
and allows you to nest conditions:

```php
<?php

$query = $query->where('bar = ?', 1)
    ->orWhere(function ($query) {
        $query->where('baz = ?', 2)
            ->where('buh = ?', 3);
    });
});

// SELECT * FROM some_table WHERE bar = 1 OR (baz = 2 AND buh = 3)

```

## Ordering
Use the `orderBy` method:

```php
<?php

$query = $query->orderBy('foo ASC');

```

Once again, you can pass multiple arguments, concatenate them yourself or call
the method multiple times.

> At this point it should be noted that, apart from the final fetch call, all
> methods can be called in any order (with the caveat that of course the order
> of multiple `where` calls might influence the resulting query).

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

## Grouping
A `Select` query can have a single `GROUP BY` clause added (i.e., if you call it
multiple times it will be overwritten). Use the `groupBy` method:

```php
<?php

$query = $query->groupBy('foo, bar');

```

In conjunction, there is also a `having` method which accepts bindable
additional parameters:

```php
<?php

$query = $query = $query->groupBy('foo, bar')
    ->having('bar > ?', 42);

```

## Unions
The `union` method accepts _another_ `Select` object to use for the union. It
will show default error handling (see below) if the selected fields are
incompatible, so that's up to you.

```php
<?php

$query = $query->union($anotherQuery);

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

$query = $query->where('id IN (SELECT foo_id FROM bar WHERE bar_id = ?)', $bar_id);
```

However, sometimes your set comes from another source and you need to manually
inject them (and the bindings) into your statement. The `Group`ed WHERE object
offers a convenience method for this:

```php
<?php

$query = $query->where(function (Group $group) use ($arrayOfPossibleValues) {
    return $group->in('field', $arrayOfPossibleValues);
});
```

Similarly, there's a `notIn` method. Like most `Where` methods, these are
chainable, so you can go as deep as you like.

## Fetching the data
Eventually, you're done building your query and will want data. Just use any
of the `fetch*` methods as you would on a `PDOStatement`. They accept the same
arguments and proxy to the underlying statement.

Quibble\Query also supports a `generate` method which returns a generator
instead (handy for large query results). Its parameters are the same as `fetch`
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
Use the `Insert` class. Its `execute` method accepts a key/value pair of values
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

Adapters implementing the `Bindable` trait have the convenience method
`insert` defined:

```php
<?php

$query = $pdo->insert('fooTable');
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
$query = $pdo->update($tableName);

$query->where('foo = ? AND bar = ?', $foo, $bar)
    ->execute(['baz' => $foobar]);
// UPDATE tableName SET baz = ? WHERE foo = ? AND bar = ?
// with bindings foo, bar and foobar.
```

## Inserting or updating raw values
Sometimes you need to insert or update "raw" SQL, e.g. `SET column = NOW()`.
Wrap the raw statement in an array to accomplish this:

```php
<?php

$pdo->insert('foo')
    ->execute(['column' => ['NOW()']]);
$pdo->update('foo')
    ->where('id = ?', $id)
    ->execute(['column' => ['NOW()']]);
```

## Deleting data
Like updating, only the `execute` method does not accept any parameters:
```php
<?php

$query = new Quibble\Query\Delete($pdo, $tableName);
// or, alternatively:
$query = $pdo->delete($tableName);

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

## Accessing the raw statement
All Query classes offer a `getStatement` method which returns the prepared
statement. This could come in useful if you need to do something really evil,
or simply if you need to pass `$driver_options` to `prepare`. The options are
the parameter to `getStatement` and are passed verbatim (the SQL is injected for
you).

Note that modifying the Query object after calling `getStatement` obviously
won't modify its previous return value.

Additionally, the Query classes also provide `getExecutedStatement` which
returns the current statement after being executed with the current bindings.
This would allow you to call more low-level methods on an already executed
statement (e.g. `getColumnMeta`).

## Creating custom query builders
Extending query classes and/or using traits, you can of course build your own
project-specific query builders! Some databases support some really exotic stuff
you might use heavily, making it worthwhile to do this.

```php
<?php

class MySelect extends Quibble\Query\Select
{
    // ...whatever custom logic you need...
}

$adapter = new class(...connection...) extends Adapter {
    use Buildable;

    public function select(string|Select $table)
    {
        return new MySelect($this, $table);
    }
}
```

