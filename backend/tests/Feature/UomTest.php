<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UomTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected UnitOfMeasure $unitCan;
    protected UnitOfMeasure $unitPack;
    protected UnitOfMeasure $unitCarton;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->unitCan    = UnitOfMeasure::create(['name' => 'Can',    'abbreviation' => 'can', 'unit_type' => 'count']);
        $this->unitPack   = UnitOfMeasure::create(['name' => 'Pack',   'abbreviation' => 'pk',  'unit_type' => 'count']);
        $this->unitCarton = UnitOfMeasure::create(['name' => 'Carton', 'abbreviation' => 'ctn', 'unit_type' => 'count']);

        $this->category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'name'        => 'Coca-Cola 330ml',
            'price'       => 0.50,
            'cost_price'  => 0.35,
            'stock_qty'   => 120,
            'category_id' => $this->category->id,
        ]);
    }

    private function attachUnits(): void
    {
        ProductUnit::create([
            'product_id'        => $this->product->id,
            'unit_id'           => $this->unitCan->id,
            'conversion_factor' => 1,
            'purchase_cost'     => 0.35,
            'selling_price'     => 0.50,
            'is_base_unit'      => true,
            'is_purchase_unit'  => true,
            'is_selling_unit'   => true,
        ]);
        ProductUnit::create([
            'product_id'        => $this->product->id,
            'unit_id'           => $this->unitPack->id,
            'conversion_factor' => 6,
            'purchase_cost'     => 2.10,
            'selling_price'     => 2.85,
            'is_base_unit'      => false,
            'is_purchase_unit'  => true,
            'is_selling_unit'   => true,
            'barcode'           => 'PACK-001',
        ]);
        ProductUnit::create([
            'product_id'        => $this->product->id,
            'unit_id'           => $this->unitCarton->id,
            'conversion_factor' => 24,
            'purchase_cost'     => 8.00,
            'selling_price'     => 10.80,
            'is_base_unit'      => false,
            'is_purchase_unit'  => true,
            'is_selling_unit'   => true,
            'barcode'           => 'CTN-001',
        ]);
        $this->product->update(['base_unit_id' => $this->unitCan->id]);
    }

    /** @test */
    public function it_sells_individual_can_and_deducts_one_base_unit(): void
    {
        $this->attachUnits();
        $puCan = ProductUnit::where('product_id', $this->product->id)
            ->where('unit_id', $this->unitCan->id)->first();

        $this->actingAs($this->admin)
            ->postJson('/api/sales', [
                'items'          => [['product_id' => $this->product->id, 'quantity' => 1, 'product_unit_id' => $puCan->id]],
                'payment_method' => 'cash',
                'tendered_amount' => 1.00,
                'tax_rate'       => 0,
            ])
            ->assertCreated();

        $this->assertEquals(119, $this->product->fresh()->stock_qty);
    }

    /** @test */
    public function it_sells_pack_of_6_and_deducts_6_base_units(): void
    {
        $this->attachUnits();
        $puPack = ProductUnit::where('product_id', $this->product->id)
            ->where('unit_id', $this->unitPack->id)->first();

        $this->actingAs($this->admin)
            ->postJson('/api/sales', [
                'items'          => [['product_id' => $this->product->id, 'quantity' => 1, 'product_unit_id' => $puPack->id]],
                'payment_method' => 'cash',
                'tendered_amount' => 5.00,
                'tax_rate'       => 0,
            ])
            ->assertCreated();

        $this->assertEquals(114, $this->product->fresh()->stock_qty);
    }

    /** @test */
    public function it_sells_2_cartons_and_deducts_48_base_units(): void
    {
        $this->attachUnits();
        $puCarton = ProductUnit::where('product_id', $this->product->id)
            ->where('unit_id', $this->unitCarton->id)->first();

        $this->actingAs($this->admin)
            ->postJson('/api/sales', [
                'items'          => [['product_id' => $this->product->id, 'quantity' => 2, 'product_unit_id' => $puCarton->id]],
                'payment_method' => 'cash',
                'tendered_amount' => 25.00,
                'tax_rate'       => 0,
            ])
            ->assertCreated();

        $this->assertEquals(72, $this->product->fresh()->stock_qty);
    }

    /** @test */
    public function it_prevents_overselling_when_stock_insufficient(): void
    {
        $this->attachUnits();
        $this->product->update(['stock_qty' => 10]);
        $puCarton = ProductUnit::where('product_id', $this->product->id)
            ->where('unit_id', $this->unitCarton->id)->first();

        $this->actingAs($this->admin)
            ->postJson('/api/sales', [
                'items'          => [['product_id' => $this->product->id, 'quantity' => 1, 'product_unit_id' => $puCarton->id]],
                'payment_method' => 'cash',
                'tendered_amount' => 15.00,
                'tax_rate'       => 0,
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    /** @test */
    public function it_saves_product_units_via_api(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/products/{$this->product->id}/units", [
                'units' => [
                    [
                        'unit_id'          => $this->unitCan->id,
                        'conversion_factor' => 1,
                        'purchase_cost'    => 0.35,
                        'selling_price'    => 0.50,
                        'is_base_unit'     => true,
                        'is_purchase_unit' => true,
                        'is_selling_unit'  => true,
                    ],
                    [
                        'unit_id'          => $this->unitCarton->id,
                        'conversion_factor' => 24,
                        'purchase_cost'    => 8.00,
                        'selling_price'    => 10.80,
                        'is_base_unit'     => false,
                        'is_purchase_unit' => true,
                        'is_selling_unit'  => true,
                    ],
                ],
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('product_units', [
            'product_id'        => $this->product->id,
            'unit_id'           => $this->unitCan->id,
            'conversion_factor' => 1.0,
        ]);
        $this->assertDatabaseHas('product_units', [
            'product_id'        => $this->product->id,
            'unit_id'           => $this->unitCarton->id,
            'conversion_factor' => 24.0,
        ]);
    }

    /** @test */
    public function it_restocks_product_in_cartons_and_converts_to_base_units(): void
    {
        $this->attachUnits();
        $puCarton    = ProductUnit::where('product_id', $this->product->id)
            ->where('unit_id', $this->unitCarton->id)->first();
        $originalStock = $this->product->stock_qty;

        $this->actingAs($this->admin)
            ->postJson("/api/products/{$this->product->id}/restock", [
                'quantity'        => 5,
                'product_unit_id' => $puCarton->id,
                'note'            => 'Test restock 5 cartons',
            ])
            ->assertOk();

        // 5 cartons × 24 = 120 base units added
        $this->assertEquals($originalStock + 120, $this->product->fresh()->stock_qty);
    }

    /** @test */
    public function it_creates_a_sale_with_unit_info_persisted_in_sale_items(): void
    {
        $this->attachUnits();
        $puCarton = ProductUnit::where('product_id', $this->product->id)
            ->where('unit_id', $this->unitCarton->id)->first();

        $response = $this->actingAs($this->admin)
            ->postJson('/api/sales', [
                'items'          => [['product_id' => $this->product->id, 'quantity' => 1, 'product_unit_id' => $puCarton->id]],
                'payment_method' => 'card',
                'tendered_amount' => 10.80,
                'tax_rate'       => 0,
            ])
            ->assertCreated();

        $sale = $response->json();
        $this->assertNotEmpty($sale['items']);
        $item = $sale['items'][0];
        $this->assertEquals('Carton', $item['unit_name']);
        $this->assertEquals(24, (float) $item['conversion_factor']);
        $this->assertEquals(10.80, (float) $item['unit_price']);
    }

    /** @test */
    public function it_lists_units_of_measure(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/units')
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'abbreviation', 'unit_type']]);
    }

    /** @test */
    public function it_creates_a_unit_of_measure(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/api/units', ['name' => 'Bottle', 'abbreviation' => 'btl', 'unit_type' => 'count'])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Bottle']);
    }

    /** @test */
    public function it_sells_product_without_configured_units_using_default_price(): void
    {
        // Product with NO product_units configured
        $this->actingAs($this->admin)
            ->postJson('/api/sales', [
                'items'          => [['product_id' => $this->product->id, 'quantity' => 2]],
                'payment_method' => 'cash',
                'tendered_amount' => 2.00,
                'tax_rate'       => 0,
            ])
            ->assertCreated();

        // Deducts 2 base units (conversion_factor defaults to 1)
        $this->assertEquals(118, $this->product->fresh()->stock_qty);
    }
}
