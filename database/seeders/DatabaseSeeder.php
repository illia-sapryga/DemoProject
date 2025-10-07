<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now('UTC')->toDateTimeString();

        // ---------------------------------------------------------------------
        // ⚙️ Fast session setup
        // ---------------------------------------------------------------------
        DB::statement("SET time_zone = '+00:00'");
        DB::statement('SET foreign_key_checks = 0');
        DB::statement('SET unique_checks = 0');
        DB::disableQueryLog();

        // ---------------------------------------------------------------------
        // 🧹 Reset storage
        // ---------------------------------------------------------------------
        Storage::deleteDirectory('public');

        // ---------------------------------------------------------------------
        // 👤 Admin user
        // ---------------------------------------------------------------------
        DB::table('users')->truncate();
        DB::table('users')->insert([
            'name' => 'Demo User',
            'email' => 'admin@admin.com',
            'password' => Hash::make('demoproject123'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->command->info('Admin user created.');

        // ---------------------------------------------------------------------
        // 🏷️ Shop Brands
        // ---------------------------------------------------------------------
        DB::table('shop_brands')->truncate();

        $brands = [];
        for ($i = 1; $i <= 20; $i++) {
            $brands[] = [
                'name' => "Brand {$i}",
                'slug' => Str::slug("brand-{$i}"),
                'website' => "https://brand{$i}.example.com",
                'description' => "Description for Brand {$i}",
                'is_visible' => true,
                'sort' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('shop_brands')->insert($brands);
        $brandIds = DB::table('shop_brands')->pluck('id')->all();
        $this->command->info('Shop brands created.');

        // ---------------------------------------------------------------------
        // 🗂️ Shop Categories (parent + children)
        // ---------------------------------------------------------------------
        DB::table('shop_categories')->truncate();

        $categories = [];
        $idCounter = 1;
        for ($i = 1; $i <= 100; $i++) {
            $parentId = $idCounter;
            $categories[] = [
                'name' => "Category {$i}",
                'slug' => Str::slug("category-{$i}"),
                'parent_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $idCounter++;

            for ($j = 1; $j <= 3; $j++) {
                $categories[] = [
                    'name' => "Category {$i}-{$j}",
                    'slug' => Str::slug("category-{$i}-{$j}"),
                    'parent_id' => $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $idCounter++;
            }
        }
        $this->bulkInsert('shop_categories', $categories);
        $categoryIds = DB::table('shop_categories')->pluck('id')->all();
        $this->command->info('Shop categories created.');

        // ---------------------------------------------------------------------
        // 👥 Customers
        // ---------------------------------------------------------------------
        DB::table('shop_customers')->truncate();

        $customers = [];
        for ($i = 1; $i <= 100000; $i++) {
            $customers[] = [
                'name' => "Customer {$i}",
                'email' => "customer{$i}@example.test",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsert('shop_customers', $customers, 5000);
        $customerIds = DB::table('shop_customers')->pluck('id')->all();
        $this->command->info('Shop customers created.');

        // ---------------------------------------------------------------------
        // 📦 Products
        // ---------------------------------------------------------------------
        DB::table('shop_products')->truncate();

        $products = [];
        $brandCount = count($brandIds);
        for ($i = 1; $i <= 5000; $i++) {
            $products[] = [
                'name' => "Product {$i}",
                'slug' => Str::slug("product-{$i}"),
                'shop_brand_id' => $brandIds[$i % $brandCount],
                'price' => mt_rand(1000, 50000) / 100,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsert('shop_products', $products, 1000);
        $productIds = DB::table('shop_products')->pluck('id')->all();
        $this->command->info('Shop products created.');

        // ---------------------------------------------------------------------
        // 🏷️ Product–Category Pivot (corrected table: shop_category_product)
        // ---------------------------------------------------------------------
        DB::table('shop_category_product')->truncate();

        $pivots = [];
        foreach ($productIds as $pid) {
            $randCats = array_rand($categoryIds, mt_rand(3, 6));
            foreach ((array) $randCats as $key) {
                $pivots[] = [
                    'shop_product_id' => $pid,
                    'shop_category_id' => $categoryIds[$key],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $this->bulkInsert('shop_category_product', $pivots, 5000);
        $this->command->info('Product-category relations created.');

        // ---------------------------------------------------------------------
        // 💳 Orders  (updated to match schema)
        // ---------------------------------------------------------------------
        DB::table('shop_orders')->truncate();

        $orders = [];
        $custCount = count($customerIds);
        $currencies = ['USD', 'EUR', 'GBP'];

        for ($i = 1; $i <= 500000; $i++) {
            $orders[] = [
                'shop_customer_id' => $customerIds[$i % $custCount],
                'number' => strtoupper(Str::random(12)),
                'total_price' => mt_rand(1000, 100000) / 100,
                'status' => collect(['new', 'processing', 'shipped', 'delivered', 'cancelled'])->random(),
                'currency' => $currencies[array_rand($currencies)],
                'shipping_price' => mt_rand(500, 2500) / 100,
                'shipping_method' => collect(['UPS', 'FedEx', 'USPS'])->random(),
                'notes' => 'Order seeded for demo',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkInsert('shop_orders', $orders, 5000);
        $orderIds = DB::table('shop_orders')->pluck('id')->all();
        $this->command->info('Shop orders created.');

        // ---------------------------------------------------------------------
        // 📦 Order Items (corrected for schema)
        // ---------------------------------------------------------------------
        DB::table('shop_order_items')->truncate();

        $items = [];
        $prodCount = count($productIds);
        $sort = 1;

        foreach ($orderIds as $oid) {
            $count = mt_rand(2, 5);
            for ($j = 0; $j < $count; $j++) {
                $pid = $productIds[($oid + $j) % $prodCount];
                $qty = mt_rand(1, 4);
                $unitPrice = mt_rand(1000, 50000) / 100;

                $items[] = [
                    'sort' => $sort++,
                    'shop_order_id' => $oid,
                    'shop_product_id' => $pid,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        $this->bulkInsert('shop_order_items', $items, 5000);
        $this->command->info('Order items created.');

        // ---------------------------------------------------------------------
        // 🧾 Comments  (updated for schema)
        // ---------------------------------------------------------------------
        DB::table('comments')->truncate();

        $comments = [];
        $custCount = count($customerIds);
        $prodCount = count($productIds);
        $now = now('UTC')->toDateTimeString();

        for ($i = 1; $i <= 200000; $i++) {
            // Alternate commentable types: half on products, half on posts
            $isProduct = $i % 2 === 0;
            $commentableType = $isProduct
                ? 'App\\Models\\Shop\\Product'
                : 'App\\Models\\Blog\\Post';

            // pick an ID depending on type
            $commentableId = $isProduct
                ? $productIds[$i % $prodCount]
                : mt_rand(1, 1000); // adjust if you have real blog_posts seeded

            $comments[] = [
                'customer_id' => $customerIds[$i % $custCount],
                'commentable_type' => $commentableType,
                'commentable_id' => $commentableId,
                'title' => "Comment Title {$i}",
                'content' => "This is seeded comment #{$i}.",
                'is_visible' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkInsert('comments', $comments, 5000);
        $this->command->info('Comments created.');

        // ---------------------------------------------------------------------
        // 📰 Blog Seeder (super-fast bulk inserts)
        // ---------------------------------------------------------------------
        $this->command->warn(PHP_EOL . 'Creating blog categories...');

        DB::table('blog_categories')->truncate();

        $blogCategories = [];
        for ($i = 1; $i <= 50; $i++) {
            $blogCategories[] = [
                'name' => "Category {$i}",
                'slug' => Str::slug("category-{$i}"),
                'description' => "This is category {$i} description.",
                'is_visible' => 1,
                'seo_title' => "SEO Title for Category {$i}",
                'seo_description' => "SEO description for Category {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsert('blog_categories', $blogCategories, 100);
        $blogCategoryIds = DB::table('blog_categories')->pluck('id')->all();
        $this->command->info('Blog categories created.');

        // ---------------------------------------------------------------------
        // ✍️ Blog Authors
        // ---------------------------------------------------------------------
        $this->command->warn(PHP_EOL . 'Creating blog authors...');

        DB::table('blog_authors')->truncate();

        $authors = [];
        for ($i = 1; $i <= 500; $i++) {
            $authors[] = [
                'name' => "Author {$i}",
                'email' => "author{$i}@example.test",
                'photo' => null,
                'bio' => "This is the bio for Author {$i}.",
                'github_handle' => "author{$i}",
                'twitter_handle' => "author{$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsert('blog_authors', $authors, 500);
        $authorIds = DB::table('blog_authors')->pluck('id')->all();
        $this->command->info('Blog authors created.');

        // ---------------------------------------------------------------------
        // 📝 Blog Posts (1,000,000 records 🚀)
        // ---------------------------------------------------------------------
        $this->command->warn(PHP_EOL . 'Creating 1,000,000 blog posts (this may take a few minutes)...');

        DB::table('blog_posts')->truncate();

        $totalPosts = 1_000_000;
        $batchSize = 5000;
        $posts = [];

        $authorCount = count($authorIds);
        $categoryCount = count($blogCategoryIds);

        for ($i = 1; $i <= $totalPosts; $i++) {
            $posts[] = [
                'blog_author_id' => $authorIds[$i % $authorCount],
                'blog_category_id' => $blogCategoryIds[$i % $categoryCount],
                'title' => "Post Title {$i}",
                'slug' => Str::slug("post-title-{$i}"),
                'content' => "This is the content for post {$i}. Generated quickly for performance testing.",
                'published_at' => now('UTC')->subDays(mt_rand(0, 365))->toDateString(),
                'seo_title' => "SEO Title {$i}",
                'seo_description' => "SEO Description {$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($i % $batchSize === 0) {
                DB::table('blog_posts')->insert($posts);
                $posts = [];

                if ($i % (50_000) === 0) {
                    $this->command->line("  → Inserted {$i} / {$totalPosts} blog posts...");
                }
            }
        }

        // Insert remaining
        if ($posts) {
            DB::table('blog_posts')->insert($posts);
        }
        $this->command->info('1,000,000 blog posts created.');

        // ---------------------------------------------------------------------
        // 🔗 Blog Links
        // ---------------------------------------------------------------------
        $this->command->warn(PHP_EOL . 'Creating blog links...');

        DB::table('blog_links')->truncate();

        $links = [];
        $colors = ['#F2A649', '#F2D59A', '#8DC63F', '#2E7D32', '#ff3951'];

        for ($i = 1; $i <= 100; $i++) {
            $links[] = [
                'url' => "https://example.com/link-{$i}",
                'title' => json_encode(['en' => "Link Title {$i}"]),
                'description' => json_encode(['en' => "This is link {$i} description"]),
                'color' => $colors[array_rand($colors)],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        $this->bulkInsert('blog_links', $links, 100);
        $this->command->info('Blog links created.');
    }

    /**
     * Insert large arrays of rows efficiently in batches.
     */
    protected function bulkInsert(string $table, array $rows, int $batchSize = 1000): void
    {
        $total = count($rows);
        $inserted = 0;

        foreach (array_chunk($rows, $batchSize) as $chunk) {
            DB::table($table)->insert($chunk);
            $inserted += count($chunk);

            // Simple progress output
            if ($total > 2000 && $inserted % (10 * $batchSize) === 0) {
                $this->command->line("  → {$table}: {$inserted}/{$total}");
            }
        }
    }
}
