# laravel-exodus

Converts YAML to actual Laravel migration files.

## Install

```bash
composer require eyf/laravel-exodus
```

## Usage

### Step 1: Create `database/migrations.yaml` file

Define a `posts` table:

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

1. Create `migrations.yaml` file (Add a `posts` table)
2. `make:migrations`
3. `migrate`
4. Edit `migrations.yaml` file (Add a `users` table)
5. `make:migrations`
6. `migrate:refresh (--seed)`
7. Edit `migrations.yaml` file
8. ... (Repeat)

### The `migrations.lock` file

By default the migration file names won't change between multiple `make:migrations` runs.

This is because Exodus keeps track of the initial migration file name in `database/migrations.lock` (to commit in your repository).

This makes sure `git` sees the edits in the same migration file throughout the whole DEV.

### The `force` option

Sometimes you may want to bypass the `migrations.lock` file (For example when you want to change the table order creation).

```bash
php artisan make:migrations --force
```

What happens:

1. All "old" migration files will be deleted (the ones in current `migrations.lock`)
2. New migration files will be generated (with newest date in filename)

## Syntax

### Column

Any column can be written fluently exactly like in the Laravel migration syntax. In fact Exodus is just a light translator of a "dot notation" `array` to the actual PHP syntax.

```yaml
my_table:
    my_column_name: string(50).nullable.unique
```

### Special column

Special column types are the "sugar" methods provided by Laravel for a better developer experience: `id()`, `timestamps()`, `softDeletes()`, `rememberToken()`, etc...

Since these column types don't have a column name (name is in the convention), just specify `true` as their value:

```yaml
my_table:
    id: true
    timestamps: true
    softDeletes: true
```

### Pivot tables

For generating a pivot table, just use two table names as follow:

```yaml
users:
    id: true
    name: string

posts:
    id: true
    title: string

"@users @posts": []
```

This will create the following pivot migration file:

```php
<?php
// database/migrations/2020_05_05_085245_create_post_user_pivot_table.php

class CreatePostUserPivotTable extends Migration
{
    public function up()
    {
        Schema::create("post_user", function (Blueprint $table) {
            $table
                ->foreignId("post_id")
                ->index()
                ->constrained();
            $table
                ->foreignId("user_id")
                ->index()
                ->constrained();
            $table->primary(["post_id", "user_id"]);
        });
    }

    public function down()
    {
        Schema::dropIfExists("post_user");
    }
}
```

You can even provide more columns to the pivot table as normal:

```yaml
"@users @posts":
    timestamps: true
    approved_by: foreignId.nullable.constrained('users')
    approved_at: timestamp.nullable
```

## Phylosophy

This package aims at speedind up development time. It is not meant to be used after you have launched to production. In fact the package does not provide a way to run migrations for adding or removing columns (it used to).

This is by choice.

While DEV'ing you should edit the `migrations.yaml` file as much as you want and run `migrate:refresh (--seed)` as often as possible. "This is the way".

By the time you are happy with your Schema, you must have launched in production, and then only you may create normal Laravel migration files for adding or removing column(s) in your tables. This is where the job of this package ends.

TODO: Implement safety guard making sure `exodus` cannot be run if it detects more migrations files than in `migrations.lock`.
