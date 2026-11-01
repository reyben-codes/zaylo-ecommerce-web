<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Product name => its categories, main one first. Products not named here keep what they have.
    public const PLACEMENTS = [
        // Home and Garden
        'Set of 4 Home Storage Bins' => ['storage_bins', 'storage_boxes'],
        'Cleaning Basket' => ['cleaning_baskets'],
        'Multi-Tier Shoe Rack' => ['shoe_racks'],
        'Indoor Plant Pot' => ['indoor_plant_pots', 'pots'],
        'Laundry Basket' => ['laundry_baskets'],
        'Hi-Care Wheeled Trash Bin' => ['trash_bins'],
        'Kenpoly Wheeled Trash Bin' => ['trash_bins'],
        'Bentley Wheeled Trash Bin' => ['trash_bins'],
        'Clothes Hanger Set' => ['clothes_hangers'],
        'Home Doormat' => ['doormats'],

        // Kitchen Appliances
        'Elite Gourmet Rice Cooker' => ['rice_cookers'],
        'Elite Gourmet Black Rice Cooker' => ['rice_cookers'],
        'Elite Gourmet White Rice Cooker' => ['rice_cookers'],
        'Professional Quiet Blender' => ['blenders'],
        'Commercial Digital Blender' => ['blenders'],
        'Evoloop 1.7L Electric Kettle' => ['electric_kettles'],
        'Green Electric Kettle' => ['electric_kettles'],
        'Touchscreen Air Fryer' => ['air_fryers'],
        'Compact Black Air Fryer' => ['air_fryers'],
        'KITfanz Electric Moka Pot' => ['coffee_maker'],
        'KITfanz Silver Electric Moka Pot' => ['coffee_maker'],
        'KITfanz Black Electric Moka Pot' => ['coffee_maker'],
        'Electric Toaster' => ['toasters'],
        'Countertop Electric Oven' => ['electric_ovens'],
        'Food Processor' => ['food_processors'],
        'Hamilton Beach Professional Hand Mixer' => ['hand_mixers'],

        // Furniture & Decor
        'Mandaue Foam Alara High-Back Office Chair' => ['office_chairs'],
        'Marble-Top Round Coffee Table' => ['coffee_tables'],
        'Apolda Natural Oak Bookshelf' => ['bookshelves'],
        'Lucent Wall Clock' => ['wall_clocks'],
        'Modern Table Lamp' => ['table_lamps'],
        'Nina Two-Drawer Bedside Table' => ['bedside_tables'],
        'Farkins TV Stand' => ['tv_stands'],
        'Harris Three-Door Shoe Cabinet' => ['shoe_cabinets', 'shoe_racks'],
        'Possini Euro Serra Floor Lamp' => ['floor_lamps'],
        'Round LED Decorative Mirror' => ['decorative_mirrors'],
        'Black Round LED Decorative Mirror' => ['decorative_mirrors'],
        'Gold Round LED Decorative Mirror' => ['decorative_mirrors'],
        'Plain Black Round Decorative Mirror' => ['decorative_mirrors'],

        // Gardening Tools
        'Bully Tools 14-Gauge Round Point Shovel' => ['garden_shovels'],
        'Total Pruning Shear' => ['pruning_shears'],
        'CT4281 Garden Rake' => ['garden_rakes'],
        '1.2L Long Spout Watering Can' => ['watering_cans'],
        'Edward Tools Garden Trowel' => ['hand_trowels'],
        'Westward 6-Inch Steel Blade Garden Hoe' => ['garden_hoes'],
        'COOLJOB Gardening Gloves - 6 Pairs' => ['garden_gloves'],
        'Keeper Electric Plant Sprayer' => ['plant_sprayers'],
        'Amtech U0725 Garden Shears' => ['garden_scissors', 'pruning_shears'],
        'Bergs Potter Simona Pot' => ['pots', 'indoor_plant_pots'],

        // Home Improvement Tools
        'ATuMan RS1 Plus 42-in-1 Screwdriver Set' => ['screwdriver_sets'],
        'Estwing Straight Claw Hammer' => ['hammers'],
        'RS PRO Steel Pliers Set PC0300' => ['pliers'],
        'RS PRO S85 5m Tape Measure' => ['measuring_tapes'],
        'Fein JMU 404 M Magnetic Base Drill' => ['drill_machines'],
        'CT6083 26-Piece Ratcheting Wrench Set' => ['wrench_sets'],
        'Stanley Compact Folding Utility Knife' => ['utility_knives'],
        'CT0110 12-Inch Spirit Level' => ['level_tools'],
        'Ingco Plastic Tool Box' => ['tool_boxes'],
        'Neilsen CT3997 Power Sander' => ['sanding_tools'],

        // Bedding & Bath
        'NATTJASMIN Fitted Sheet' => ['bedsheets'],
        'LÖKSTAMFLY Ergonomic Pillow' => ['pillows'],
        'Yedwo Double Layer Knitted Blanket' => ['blankets'],
        'Luxury Spa Collection Luxe Towel' => ['bath_towels'],
        'Liana Geometric Shower Curtain' => ['shower_curtains'],
        'Kathy Ireland 6-Piece Comforter Set' => ['comforters'],
        'Secret Linen Store Linen Pillowcase' => ['pillowcases'],
        'LUDDROS Mattress Protector' => ['mattress_protectors'],
        'IKEA TOFTBO Bath Mat' => ['bath_mats'],
        '3-Layer Bathroom Storage Rack' => ['bathroom_storage_racks'],
    ];

    // Registered shop categories with a close match in the finalized list.
    private const SHOP_CATEGORIES = ['home_living' => 'home_garden', 'electronics' => 'kitchen_appliances'];

    public function up(): void
    {
        if (! Schema::hasColumn('products', 'category')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (self::PLACEMENTS as $name => $categories) {
                foreach (DB::table('products')->where('name', $name)->pluck('id') as $productId) {
                    DB::table('products')->where('id', $productId)->update(['category' => $categories[0]]);
                    DB::table('product_categories')->where('product_id', $productId)->delete();
                    foreach (array_slice($categories, 1) as $category) {
                        DB::table('product_categories')->insert([
                            'product_id' => $productId, 'category' => $category, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }

            foreach (self::SHOP_CATEGORIES as $old => $new) {
                DB::table('seller_applications')->where('shop_category', $old)->update(['shop_category' => $new]);
            }
        });
    }

    // The previous categories no longer exist, so there is nothing to move back to.
    public function down(): void
    {
    }
};
