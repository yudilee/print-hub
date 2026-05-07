<?php

namespace Tests\Feature;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use App\Models\PrintDocument;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected ClientApp $clientApp;
    protected PrintAgent $agent;
    protected string $rawApiKey;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rawApiKey = '550e8400-e29b-41d4-a716-446655440000';

        // Use sha256 hash because ClientApp::findByKey() first tries
        // a sha256 lookup against the api_key column.
        $this->clientApp = ClientApp::create([
            'name'      => 'Test App',
            'api_key'   => hash('sha256', $this->rawApiKey),
            'is_active' => true,
        ]);

        $this->agent = PrintAgent::create([
            'name'         => 'Test Agent',
            'agent_key'    => hash('sha256', Str::random(32)),
            'is_active'    => true,
            'ip_address'   => '127.0.0.1',
            'last_seen_at' => now(),
            'printers'     => ['Test Printer'],
        ]);

        $this->user = User::create([
            'name'     => 'Test User',
            'email'    => 'user@example.com',
            'password' => bcrypt('password'),
            'role'     => 'user',
        ]);
    }

    protected function apiHeaders(): array
    {
        return ['X-API-Key' => $this->rawApiKey];
    }

    public function test_document_upload_creates_document_record()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'document' => [
                    'id', 'original_name', 'mime_type', 'file_size', 'formatted_size',
                ],
            ],
        ]);

        $this->assertDatabaseHas('print_documents', [
            'original_name' => 'document.pdf',
            'mime_type'     => 'application/pdf',
        ]);
    }

    public function test_document_upload_rejects_invalid_file_type()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('document.exe', 100, 'application/x-msdownload');

        $response = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function test_document_retrieval_returns_document()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $uploadResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $documentId = $uploadResponse->json('data.document.id');

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/v1/documents/{$documentId}");

        $response->assertOk();
        $response->assertJsonPath('data.document.original_name', 'test.pdf');
    }

    public function test_document_list_returns_all_documents()
    {
        Storage::fake('local');

        // Upload two documents
        $file1 = UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf');
        $file2 = UploadedFile::fake()->create('doc2.pdf', 200, 'application/pdf');

        $this->withHeaders($this->apiHeaders())->postJson('/api/v1/documents/upload', ['file' => $file1]);
        $this->withHeaders($this->apiHeaders())->postJson('/api/v1/documents/upload', ['file' => $file2]);

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson('/api/v1/documents');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.documents');
    }

    public function test_document_deletion_soft_deletes()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('delete-me.pdf', 100, 'application/pdf');

        $uploadResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $documentId = $uploadResponse->json('data.document.id');

        $response = $this->withHeaders($this->apiHeaders())
            ->deleteJson("/api/v1/documents/{$documentId}");

        $response->assertOk();

        // Document should be soft-deleted
        $this->assertSoftDeleted('print_documents', ['id' => $documentId]);
    }

    public function test_document_association_with_print_job()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('job-doc.pdf', 100, 'application/pdf');

        $uploadResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $documentId = $uploadResponse->json('data.document.id');

        // Create a print job associated with this document
        $job = PrintJob::create([
            'job_id'         => (string) Str::uuid(),
            'print_agent_id' => $this->agent->id,
            'document_id'    => $documentId,
            'printer_name'   => 'Test Printer',
            'type'           => 'pdf',
            'status'         => 'pending',
            'file_path'      => 'print_jobs/test.pdf',
        ]);

        $this->assertEquals($documentId, $job->document_id);

        // Verify the relationship works
        $this->assertNotNull($job->document);
        $this->assertEquals('job-doc.pdf', $job->document->original_name);
    }

    public function test_document_preview_returns_file_content()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('preview.pdf', 100, 'application/pdf');

        $uploadResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $documentId = $uploadResponse->json('data.document.id');

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/v1/documents/{$documentId}/preview");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="preview.pdf"');
    }

    public function test_document_download_returns_attachment()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('download.pdf', 100, 'application/pdf');

        $uploadResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $documentId = $uploadResponse->json('data.document.id');

        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/v1/documents/{$documentId}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'attachment; filename="download.pdf"');
    }

    public function test_deleted_document_returns_404()
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('ghost.pdf', 100, 'application/pdf');

        $uploadResponse = $this->withHeaders($this->apiHeaders())
            ->postJson('/api/v1/documents/upload', [
                'file' => $file,
            ]);

        $documentId = $uploadResponse->json('data.document.id');

        // Delete the document
        $this->withHeaders($this->apiHeaders())
            ->deleteJson("/api/v1/documents/{$documentId}");

        // Trying to retrieve it should 404
        $response = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/v1/documents/{$documentId}");

        $response->assertStatus(404);
    }
}
