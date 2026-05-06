<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\ClientApp;
use App\Models\Company;
use App\Models\DataSchema;
use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return; // Safety: never seed demo data in production
        }

        // Find or create demo company/branch
        $company = Company::firstOrCreate(
            ['code' => 'DEMO'],
            ['name' => 'Demo Company']
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'DEMO-MAIN'],
            ['company_id' => $company->id, 'name' => 'Demo Branch']
        );

        // Create a demo agent
        $rawAgentKey = 'demo-agent-key-32-chars-long!!';
        $agent = PrintAgent::firstOrCreate(
            ['name' => 'Demo Workstation'],
            [
                'agent_key'  => PrintAgent::hashKey($rawAgentKey),
                'branch_id'  => $branch->id,
                'location'   => 'Front Office',
                'department' => 'Sales',
                'is_active'  => true,
                'printers'   => ['Default Printer', 'HP LaserJet Pro'],
            ]
        );

        // Create demo profiles
        $invoiceProfile = PrintProfile::firstOrCreate(
            ['name' => 'demo_invoice'],
            [
                'description'        => 'Demo Invoice Queue',
                'branch_id'          => $branch->id,
                'print_agent_id'     => $agent->id,
                'paper_size'         => 'A4',
                'orientation'        => 'portrait',
                'copies'             => 1,
                'duplex'             => 'one-sided',
                'default_printer'    => 'Default Printer',
                'margin_top'         => 4.23,
                'margin_bottom'      => 4.23,
                'margin_left'        => 4.23,
                'margin_right'       => 4.23,
                'tray_source'        => 'auto',
                'color_mode'         => 'monochrome',
                'print_quality'      => 'normal',
                'scaling_percentage' => 100,
                'extra_options'      => ['fit_to_page' => true],
            ]
        );

        $receiptProfile = PrintProfile::firstOrCreate(
            ['name' => 'demo_receipt'],
            [
                'description'        => 'Demo Receipt Queue (Half Letter)',
                'branch_id'          => $branch->id,
                'print_agent_id'     => $agent->id,
                'paper_size'         => 'Half Letter',
                'orientation'        => 'portrait',
                'copies'             => 1,
                'duplex'             => 'one-sided',
                'default_printer'    => 'Default Printer',
                'margin_top'         => 0,
                'margin_bottom'      => 0,
                'margin_left'        => 0,
                'margin_right'       => 0,
                'scaling_percentage' => 100,
            ]
        );

        // Create demo data schema
        $schema = DataSchema::firstOrCreate(
            ['schema_name' => 'demo_invoice'],
            [
                'version'     => 1,
                'is_latest'   => true,
                'label'       => 'Demo Invoice Schema',
                'fields'      => [
                    'invoice_no'   => ['type' => 'string', 'label' => 'Invoice Number', 'required' => true],
                    'customer'     => ['type' => 'string', 'label' => 'Customer Name', 'required' => true],
                    'date'         => ['type' => 'date', 'label' => 'Date', 'format' => 'dd/MM/yyyy'],
                    'total'        => ['type' => 'number', 'label' => 'Total', 'format' => 'currency', 'currency_code' => 'IDR', 'decimal_places' => 2],
                ],
                'tables' => [
                    'items' => [
                        'columns' => [
                            'description' => ['type' => 'string'],
                            'qty'         => ['type' => 'number', 'format' => 'integer'],
                            'price'       => ['type' => 'number', 'format' => 'currency'],
                            'subtotal'    => ['type' => 'number', 'format' => 'currency', 'computed' => 'qty * price'],
                        ],
                        'min_rows' => 1,
                    ],
                ],
                'sample_data' => [
                    'invoice_no' => 'INV-2026-001',
                    'customer'   => 'PT Demo Indonesia',
                    'date'       => '2026-04-30',
                    'total'      => 15000000,
                    'items'      => [
                        ['description' => 'Oil Change', 'qty' => 2, 'price' => 500000],
                        ['description' => 'Brake Pads', 'qty' => 1, 'price' => 1200000],
                    ],
                ],
            ]
        );

        // Create demo templates
        PrintTemplate::firstOrCreate(
            ['name' => 'demo_invoice'],
            [
                'data_schema_id'      => $schema->id,
                'data_schema_version' => 1,
                'paper_width_mm'      => 210,
                'paper_height_mm'     => 297,
                'elements'            => [
                    ['type' => 'label', 'text' => 'INVOICE', 'x' => 80, 'y' => 10, 'font_size' => 16, 'bold' => true],
                    ['type' => 'field', 'key' => 'invoice_no', 'x' => 140, 'y' => 25, 'font_size' => 10],
                    ['type' => 'field', 'key' => 'date', 'x' => 140, 'y' => 30, 'font_size' => 10],
                    ['type' => 'field', 'key' => 'customer', 'x' => 10, 'y' => 35, 'font_size' => 12, 'bold' => true],
                    ['type' => 'table', 'key' => 'items', 'x' => 10, 'y' => 50, 'row_height' => 6, 'header_height' => 7,
                        'columns' => [
                            ['key' => 'description', 'label' => 'Description', 'width' => 80],
                            ['key' => 'qty', 'label' => 'Qty', 'width' => 20],
                            ['key' => 'price', 'label' => 'Price', 'width' => 40],
                            ['key' => 'subtotal', 'label' => 'Subtotal', 'width' => 40],
                        ],
                    ],
                    ['type' => 'field', 'key' => 'total', 'x' => 140, 'y' => 80, 'font_size' => 11, 'bold' => true],
                    ['type' => 'line', 'x' => 10, 'y' => 78, 'width' => 190, 'height' => 0.3],
                ],
            ]
        );

        // Create demo client app
        $rawApiKey = 'demo-api-key-550e8400-e29b-41d4-a716-446655440000';
        ClientApp::firstOrCreate(
            ['name' => 'Demo App'],
            [
                'api_key'         => ClientApp::hashKey($rawApiKey),
                'is_active'       => true,
                'allowed_origins' => ['http://localhost:*'],
            ]
        );

        $this->command?->info('Demo data seeded successfully!');
        $this->command?->info("  Agent key: {$rawAgentKey}");
        $this->command?->info("  Client API key: {$rawApiKey}");
    }
}
