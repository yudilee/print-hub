<?php

namespace Database\Seeders;

use App\Models\DataSchema;
use App\Models\PrintTemplate;
use Illuminate\Database\Seeder;

class OdooStarterTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Surat Jalan / Delivery Slip Schema & Template (Continuous Form 9.5 x 11 inch = 241.3 x 279.4 mm)
        $sjSchema = DataSchema::firstOrCreate(
            ['schema_name' => 'odoo_surat_jalan'],
            [
                'label'       => 'Odoo Delivery Slip / Surat Jalan',
                'version'     => 1,
                'is_latest'   => true,
                'fields'      => [
                    'picking_number'  => ['type' => 'string', 'label' => 'No. Surat Jalan'],
                    'date_done'       => ['type' => 'date', 'format' => 'd/m/Y', 'label' => 'Tanggal'],
                    'customer_name'   => ['type' => 'string', 'label' => 'Nama Customer / Tujuan'],
                    'customer_address'=> ['type' => 'string', 'label' => 'Alamat Pengiriman'],
                    'vehicle_number'  => ['type' => 'string', 'label' => 'No. Kendaraan'],
                    'driver_name'     => ['type' => 'string', 'label' => 'Nama Supir'],
                    'notes'           => ['type' => 'string', 'label' => 'Catatan'],
                ],
                'tables'      => [
                    'items' => [
                        'label'   => 'Daftar Barang',
                        'columns' => [
                            ['key' => 'product_code', 'label' => 'Kode', 'type' => 'string'],
                            ['key' => 'product_name', 'label' => 'Nama Barang', 'type' => 'string'],
                            ['key' => 'quantity',     'label' => 'Qty', 'type' => 'number', 'format' => '#,##0'],
                            ['key' => 'uom',          'label' => 'Satuan', 'type' => 'string'],
                        ]
                    ]
                ],
                'sample_data' => [
                    'picking_number'   => 'WH/OUT/2026/00142',
                    'date_done'        => date('Y-m-d'),
                    'customer_name'    => 'PT Sumber Makmur Jaya',
                    'customer_address' => 'Jl. Industri Raya Blok C2 No. 8, Cikarang, Bekasi',
                    'vehicle_number'   => 'B 9128 UYZ',
                    'driver_name'      => 'Ahmad Suhendra',
                    'notes'            => 'Harap diterima sebelum jam 17:00 WIB',
                    'items'            => [
                        ['product_code' => 'BRG-001', 'product_name' => 'Kertas Continuous Form 9.5x11 3-Ply', 'quantity' => 10, 'uom' => 'Box'],
                        ['product_code' => 'BRG-002', 'product_name' => 'Ribbon Cartridge Epson LQ-2190',     'quantity' => 5,  'uom' => 'Pcs'],
                        ['product_code' => 'BRG-003', 'product_name' => 'Thermal Label Roll 100x150mm',       'quantity' => 20, 'uom' => 'Roll'],
                    ]
                ]
            ]
        );

        PrintTemplate::firstOrCreate(
            ['name' => 'odoo_surat_jalan_continuous'],
            [
                'data_schema_id'  => $sjSchema->id,
                'paper_width_mm'  => 241.3,
                'paper_height_mm' => 279.4,
                'elements'        => [
                    [
                        'type' => 'text',
                        'content' => 'SURAT JALAN / PENGIRIMAN BARANG',
                        'x' => 10, 'y' => 15, 'font_size' => 14, 'bold' => true
                    ],
                    [
                        'type' => 'field',
                        'key' => 'picking_number',
                        'x' => 170, 'y' => 15, 'font_size' => 11, 'bold' => true,
                        'prefix' => 'No: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'customer_name',
                        'x' => 10, 'y' => 28, 'font_size' => 10, 'bold' => true,
                        'prefix' => 'Kepada: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'customer_address',
                        'x' => 10, 'y' => 34, 'font_size' => 9, 'bold' => false,
                        'prefix' => 'Alamat: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'date_done',
                        'x' => 170, 'y' => 28, 'font_size' => 9, 'bold' => false,
                        'prefix' => 'Tgl: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'vehicle_number',
                        'x' => 170, 'y' => 34, 'font_size' => 9, 'bold' => false,
                        'prefix' => 'No. Pol: '
                    ],
                    [
                        'type' => 'multipage_table',
                        'key' => 'items',
                        'x' => 10, 'y' => 45, 'bottom_padding' => 40,
                        'header_height' => 8, 'row_height' => 7, 'font_size' => 9,
                        'columns' => [
                            ['key' => 'product_code', 'label' => 'Kode', 'width' => 40, 'align' => 'L'],
                            ['key' => 'product_name', 'label' => 'Nama Barang', 'width' => 120, 'align' => 'L'],
                            ['key' => 'quantity',     'label' => 'Qty', 'width' => 30, 'align' => 'R'],
                            ['key' => 'uom',          'label' => 'Satuan', 'width' => 30, 'align' => 'C'],
                        ]
                    ],
                    [
                        'type' => 'text',
                        'content' => "Penerima,\n\n\n( ............................. )",
                        'x' => 20, 'y' => 240, 'font_size' => 9
                    ],
                    [
                        'type' => 'text',
                        'content' => "Pengirim / Supir,\n\n\n( ............................. )",
                        'x' => 100, 'y' => 240, 'font_size' => 9
                    ],
                    [
                        'type' => 'text',
                        'content' => "Hormat Kami,\n\n\n( ............................. )",
                        'x' => 180, 'y' => 240, 'font_size' => 9
                    ]
                ]
            ]
        );

        // 2. Thermal Shipping Label Schema & Template (100x150mm)
        $labelSchema = DataSchema::firstOrCreate(
            ['schema_name' => 'odoo_thermal_shipping_label'],
            [
                'label'       => 'Odoo Thermal Shipping Label 100x150mm',
                'version'     => 1,
                'is_latest'   => true,
                'fields'      => [
                    'tracking_ref'     => ['type' => 'string', 'label' => 'No. Resi / Tracking'],
                    'barcode'          => ['type' => 'string', 'label' => 'Barcode Content'],
                    'recipient_name'   => ['type' => 'string', 'label' => 'Nama Penerima'],
                    'recipient_phone'  => ['type' => 'string', 'label' => 'No. Telp Penerima'],
                    'recipient_address'=> ['type' => 'string', 'label' => 'Alamat Lengkap'],
                    'sender_name'      => ['type' => 'string', 'label' => 'Nama Pengirim'],
                    'weight_kg'        => ['type' => 'number', 'format' => '#,##0.0', 'label' => 'Berat (Kg)'],
                ],
                'tables'      => [],
                'sample_data' => [
                    'tracking_ref'      => 'EXP-8891029381',
                    'barcode'           => 'EXP8891029381',
                    'recipient_name'    => 'Bpk. Hendra Gunawan',
                    'recipient_phone'   => '0812-3456-7890',
                    'recipient_address' => 'Gedung Wisma Niaga Lt. 4, Jl. Jend. Sudirman Kav. 25, Jakarta Selatan',
                    'sender_name'       => 'Print Hub Logistics Warehouse',
                    'weight_kg'         => 2.5,
                ]
            ]
        );

        PrintTemplate::firstOrCreate(
            ['name' => 'odoo_thermal_shipping_label'],
            [
                'data_schema_id'  => $labelSchema->id,
                'paper_width_mm'  => 100.0,
                'paper_height_mm' => 150.0,
                'elements'        => [
                    [
                        'type' => 'text',
                        'content' => 'SHIPPING / EXPEDITION LABEL',
                        'x' => 5, 'y' => 10, 'font_size' => 11, 'bold' => true
                    ],
                    [
                        'type' => 'barcode',
                        'key' => 'barcode',
                        'barcode_type' => 'code128',
                        'x' => 15, 'y' => 18, 'width' => 70, 'height' => 18
                    ],
                    [
                        'type' => 'field',
                        'key' => 'tracking_ref',
                        'x' => 5, 'y' => 40, 'font_size' => 10, 'bold' => true,
                        'prefix' => 'RESI: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'recipient_name',
                        'x' => 5, 'y' => 52, 'font_size' => 10, 'bold' => true,
                        'prefix' => 'PENERIMA: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'recipient_phone',
                        'x' => 5, 'y' => 59, 'font_size' => 9, 'bold' => false,
                        'prefix' => 'TELP: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'recipient_address',
                        'x' => 5, 'y' => 66, 'font_size' => 9, 'bold' => false
                    ],
                    [
                        'type' => 'field',
                        'key' => 'sender_name',
                        'x' => 5, 'y' => 110, 'font_size' => 9, 'bold' => false,
                        'prefix' => 'PENGIRIM: '
                    ],
                    [
                        'type' => 'field',
                        'key' => 'weight_kg',
                        'x' => 5, 'y' => 120, 'font_size' => 10, 'bold' => true,
                        'prefix' => 'BERAT: ',
                        'suffix' => ' KG'
                    ]
                ]
            ]
        );
    }
}
