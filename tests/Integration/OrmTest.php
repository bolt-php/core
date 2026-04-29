<?php

namespace framework\tests\Integration;

use framework\db\ActiveModel;
use framework\models\attributes\PrimaryKey;
use framework\tests\DatabaseTestCase;

class Product extends ActiveModel
{
    #[PrimaryKey]
    public int $id;

    public string $title;
}

class OrmTest extends DatabaseTestCase
{
    protected function createSchema(): void
    {
        db()->execute('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT)');
    }

    public function testInsertSetsId(): void
    {
        $product = new Product();
        $product->title = 'Test';

        $product->save();

        $this->assertGreaterThan(0, $product->id);
    }

    public function testInsertedProductCanBeRetrieved(): void
    {
        $product = new Product();
        $product->title = 'Widget';

        $product->save();

        $found = Product::find($product->id);

        $this->assertEquals('Widget', $found->title);
    }

    public function testFirst(): void
    {
        $product = new Product();
        $product->title = 'Test';

        $product->save();

        $product = Product::select()
            ->first();

        $this->assertIsString($product->title);
    }
}
