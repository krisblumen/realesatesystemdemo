<?php

namespace Tests\Feature\Frontend;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sitemap.xml is built from the canonical route allowlist (RFC-076): it
 * always serves valid XML with the institutional pages, and never lists a route
 * that does not resolve.
 */
class FrontendSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_serves_valid_xml_with_the_institutional_routes(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml');

        $body = $response->getContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $body);
        $this->assertStringContainsString('<urlset', $body);

        // Every institutional page in the allowlist resolves and is listed.
        $this->assertStringContainsString(route('home'), $body);
        $this->assertStringContainsString(route('nosotros'), $body);
        $this->assertStringContainsString(route('servicios'), $body);
        $this->assertStringContainsString(route('leads.create'), $body);
    }
}
