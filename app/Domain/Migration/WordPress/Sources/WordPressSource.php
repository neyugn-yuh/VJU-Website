<?php

namespace App\Domain\Migration\WordPress\Sources;

/**
 * A WordPress data source producing normalized records. Two implementations:
 * DatabaseSource (authoritative: dump restored into the "wordpress" connection) and
 * RestApiSource (public REST API: fast to start, no private meta/menus/users).
 *
 * Normalized shapes:
 *  user:    {id, name, email?, login?, registered?}
 *  term:    {id, taxonomy, name, slug, description, parent, lang?, translations{lang:id}, link?}
 *  media:   {id, url, title, alt, caption, description, mime, date_gmt, lang?, file?}
 *  post:    {id, type, status, lang?, translations{lang:id}, parent, slug, title, excerpt, content,
 *            content_format(rendered|raw|elementor), elementor?, author, date_gmt, modified_gmt,
 *            featured_media, categories[], tags[], comment_status, menu_order, link?, template,
 *            seo{title,description,keywords,canonical,og_title,og_description,og_image,noindex,nofollow}?, meta{}}
 *  menu:    {id, name, location?, lang?, items[{id, parent, title, url, type, object, object_id, target, order}]}
 *  comment: {id, post, parent, author_name, author_email, content, date_gmt, status}
 */
interface WordPressSource
{
    public function name(): string;

    /** @return iterable<array> */
    public function users(): iterable;

    /** @return iterable<array> */
    public function terms(string $taxonomy): iterable;

    /** @param array{since?: ?string, id?: ?int, limit?: ?int} $filters
     *  @return iterable<array> */
    public function media(array $filters = []): iterable;

    /** @param array{since?: ?string, id?: ?int, limit?: ?int, locale?: ?string} $filters
     *  @return iterable<array> */
    public function posts(string $type, array $filters = []): iterable;

    /** @return iterable<array> */
    public function menus(): iterable;

    /** @return iterable<array> */
    public function comments(array $filters = []): iterable;

    /** Existing redirect rules (Yoast Premium Redirect Manager): {origin, target, status, format(plain|regex)} */
    public function redirects(): iterable;

    /** Inventory for wp:inspect and reconciliation: counts by type/locale, plugins, etc. */
    public function inventory(): array;
}
