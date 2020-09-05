# laravel-exodus

Converts YAML migrations to files.

## Install

```bash
composer require eyf/laravel-exodus
```

## Usage

### Step 1: Create `database/migrations.yaml` file

Let's write some hypothetical `posts` table:

```yaml
# database/migrations.yaml

posts:
    id: true
    timestamps: true
    softDeletes: true
    slug: string.unique
    title: string
    content: text
    excerpt: string.nullable
    author_id: foreignId.constrained('users')
```

### Step 2: Make migrations (command)

Run Exodus `make:migrations` command to translate the `yaml` file into actual Laravel migration files:

```bash
php artisan make:migrations

Created Migration: 2020_05_05_100005_create_posts_table
```

### Step 3: `migrate` as normal

```bash
php artisan migrate
```

## Workflow

Exodus is a DEV package, it is meant to ease / speed up development time.

A normal workflow while DEV'ing could be:

1. Create `migrations.yaml` file
2. `make:migrations`
3. `migrate`
4. Edit `migrations.yaml` file
5. `make:migrations`
6. `migrate:refresh (--seed)`
7. Edit `migrations.yaml` file
8. ... (Repeat)

### `migrations.lock` file

As you may have noticed if you ran a similar example as above, the migration file names don't change between several `make:migrations` commands.

This is because Exodus keeps track of file names in the `database/migrations.lock` file (to commit in your repository). While in DEV you want to iterate fast through different table schemas but do not want to create a new migration file for every column change.

_Note_: When you edit a YAML migration, you obviously need to run `migrate:refresh (--seed)` for the changes to be reflected in your database.

### The `force` option

Sometimes you may need to bypass the `migrations.lock` file. For example when you want to change table order creation.

```bash
php artisan make:migrations --force
```

What happens:

1. All "old" migration files will be deleted (the ones in current `migrations.lock`)
2. New migration files will be generated (with newest date in filename)

## Syntax

### Column

Any column can be written fluently exactly like in the Laravel migration syntax. In fact Exodus is just a light translator of an `array` to the PHP syntax.

```yaml
my_table:
    my_column_name: string(50).nullable.unique
```

### Special column

Special column types are the "sugar" methods provided by Laravel for a better developer experience: `id()`, `timestamps()`, `softDeletes()`, `rememberToken()`, etc...

Since these column types dont need a column name (name is in convention), just specify `true` as their value:

```yaml
my_table:
    id: true
    timestamps: true
    softDeletes: true
```

### `add`/`remove` column migrations

When your schema is stable and you have already deployed it to production, it may be time to add or remove columns from tables.

Exodus provides a short syntaxt to deal with those:

```yaml
my_table:
  id: true
  # ...

add@my_table
  some_column: smallInteger.nullable

another_table:
  id: true
  title: string.unique
  # ...

# more migrations...

remove@another_table
  title: string.unique
```

_Note_: In a `remove` migration, you still need to provide the full `column` type for the `down` side of the migration (revert / rollback).

### Pivot tables

For generating a pivot table automatically, just use two table names:

```yaml
users:
    id: true
    name: string

posts:
    id: true
    title: string

"@users @posts": []
```

This will create the following pivot migration file (redacted for clarity):

```php
<?php
// database/migrations/2020_05_05_085245_create_post_user_pivot_table.php

class CreatePostUserPivotTable extends Migration
{
    public function up()
    {
        Schema::create('post_user', function (Blueprint $table) {
            $table
                ->foreignId('post_id')
                ->index()
                ->constrained();
            $table
                ->foreignId('user_id')
                ->index()
                ->constrained();
            $table->primary(['post_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('post_user');
    }
}
```

You can even provide more columns to the pivot as normal:

```yaml
"@users @posts":
    timestamps: true
    approved_by: foreignId.nullable.constrained('users')
    approved_at: timestamp.nullable
```

### Custom migration

All the above migrations can be written with this default syntax. All the above migrations are just shortcuts to the "normal" migration syntax:

```yaml
my_custom_migration:
    table: some_table_name

    up:
        column_name: string.unique
        another_name: boolean

    down:
        column_name: dropColumn
        another_name: dropColumn
```
