<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('site.site_name', ['vi' => 'Trường Đại học Việt Nhật - ĐHQGHN', 'en' => 'VNU Vietnam Japan University', 'ja' => 'ベトナム国家大学ハノイ校日越大学']);
        $this->migrator->add('site.site_description', ['vi' => 'Trường Đại học Việt Nhật, Đại học Quốc gia Hà Nội', 'en' => 'VNU Vietnam Japan University', 'ja' => '日越大学']);
        $this->migrator->add('site.default_locale', 'vi');
        $this->migrator->add('site.logo_media_id', null);
        $this->migrator->add('site.home_page_id', null);

        $this->migrator->add('contact.phone', null);
        $this->migrator->add('contact.email', null);
        $this->migrator->add('contact.address', ['vi' => '', 'en' => '', 'ja' => '']);
        $this->migrator->add('contact.map_url', null);

        foreach (['facebook', 'youtube', 'linkedin', 'instagram', 'tiktok'] as $network) {
            $this->migrator->add("social.$network", null);
        }

        $this->migrator->add('seo.default_title', ['vi' => 'Trường Đại học Việt Nhật', 'en' => 'VNU Vietnam Japan University', 'ja' => '日越大学']);
        $this->migrator->add('seo.default_description', ['vi' => '', 'en' => '', 'ja' => '']);
        $this->migrator->add('seo.default_og_image_id', null);
        $this->migrator->add('seo.title_template', '%title% - %site%');
        $this->migrator->add('seo.robots_extra', null);
        $this->migrator->add('seo.discourage_indexing', false);

        $this->migrator->add('analytics.ga_measurement_id', null);
        $this->migrator->add('analytics.gtm_container_id', null);
    }
};
