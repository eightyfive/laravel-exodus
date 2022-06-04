<?php
namespace Eyf\Exodus;

use PHPUnit\Framework\TestCase;
use Eyf\Exodus\Exodus;

class ExodusTest extends TestCase
{
    protected $exodus;

    protected function setUp(): void
    {
        $this->exodus = new Exodus();
    }

    public function testParseTables()
    {
        $migrations = $this->exodus->parse([
            "users" => [
                "id" => true,
                "username" => "string(10).nullable",
                "age" => "smallInteger.unsigned.default(0)",
            ],

            "posts" => [
                "id" => true,
                "title" => "string(100)",
                "user_id" => "foreignId.constrained",
                "blogId" => "foreignId.constrained('blogs')",
            ],

            "@users @posts" => [
                "timestamps" => true,
                "written_at" => "timestamp",
            ],
        ]);

        $this->assertEquals(count($migrations), 3);

        // Users
        $this->assertEquals($migrations[0]["name"], "create_users_table");

        $this->assertStringContainsString(
            'Schema::create(\'users\', ',
            $migrations[0]["up"]
        );
        $this->assertStringContainsString(
            '$table->id();',
            $migrations[0]["up"]
        );
        $this->assertStringContainsString(
            '$table->string(\'username\', 10)->nullable();',
            $migrations[0]["up"]
        );
        $this->assertStringContainsString(
            '$table->smallInteger(\'age\')->unsigned()->default(0);',
            $migrations[0]["up"]
        );

        // Posts
        $this->assertEquals($migrations[1]["name"], "create_posts_table");

        $this->assertStringContainsString(
            'Schema::create(\'posts\', ',
            $migrations[1]["up"]
        );
        $this->assertStringContainsString(
            '$table->id();',
            $migrations[1]["up"]
        );
        $this->assertStringContainsString(
            '$table->string(\'title\', 100);',
            $migrations[1]["up"]
        );
        $this->assertStringContainsString(
            '$table->foreignId(\'user_id\')->constrained();',
            $migrations[1]["up"]
        );
        $this->assertStringContainsString(
            '$table->foreignId(\'blogId\')->constrained(\'blogs\');',
            $migrations[1]["up"]
        );

        // Users <> Posts (Pivot)
        $this->assertEquals(
            $migrations[2]["name"],
            "create_post_user_pivot_table"
        );

        $this->assertStringContainsString(
            'Schema::create(\'post_user\', ',
            $migrations[2]["up"]
        );
        $this->assertStringContainsString(
            '$table->foreignId(\'post_id\')->constrained()->onDelete(\'cascade\');',
            $migrations[2]["up"]
        );
        $this->assertStringContainsString(
            '$table->foreignId(\'user_id\')->constrained()->onDelete(\'cascade\');',
            $migrations[2]["up"]
        );
        $this->assertStringContainsString(
            '$table->primary([\'post_id\', \'user_id\']);',
            $migrations[2]["up"]
        );
        $this->assertStringContainsString(
            '$table->timestamps();',
            $migrations[2]["up"]
        );
        $this->assertStringContainsString(
            '$table->timestamp(\'written_at\');',
            $migrations[2]["up"]
        );
    }
}
