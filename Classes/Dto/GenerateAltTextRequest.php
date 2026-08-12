<?php

declare(strict_types=1);

namespace AutoAltAi\AltTextGenerator\Dto;

final readonly class GenerateAltTextRequest
{
    public function __construct(
        public string $imageUrl,
        public string $websiteDomain,
        public string $language = 'en',
        public ?string $base64Image = null,
        public string $writingStyle = 'default',
        public string $seoKeywords = '',
        public string $negativeKeywords = '',
        public string $prefix = '',
        public string $suffix = '',
        public string $customPrompt = '',
        public int $altTextMinLimit = 0,
        public int $altTextMaxLimit = 0,
        public string $productName = '',
        public bool $generateTitle = false,
        public bool $generateDescription = false,
        public bool $renameFile = false,
        public bool $generateAltText = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'image_url' => $this->imageUrl,
            'language' => $this->language,
            'ai_writing_style' => $this->writingStyle,
            'seo_keywords' => $this->seoKeywords,
            'negative_keywords' => $this->negativeKeywords,
            'hardcoded_string_begin' => $this->prefix,
            'hardcoded_string_end' => $this->suffix,
            'customPromptFromUser' => $this->customPrompt,
            'website_domain' => $this->websiteDomain,
        ];

        if ($this->base64Image !== null && $this->base64Image !== '') {
            $payload['base64_img'] = $this->base64Image;
        }
        if ($this->altTextMinLimit > 0) {
            $payload['alttext_min_limit'] = $this->altTextMinLimit;
        }
        if ($this->altTextMaxLimit > 0) {
            $payload['alttext_max_limit'] = $this->altTextMaxLimit;
        }
        if ($this->productName !== '') {
            $payload['product_name'] = $this->productName;
        }
        if ($this->generateTitle) {
            $payload['autoaltai_title'] = 'on';
        }
        if ($this->generateDescription) {
            $payload['autoaltai_description'] = 'on';
        }
        if ($this->renameFile) {
            $payload['autoaltai_rename_file'] = 'on';
        }
        if (!$this->generateAltText) {
            $payload['autoaltai_alt_text'] = 'off';
        }

        return $payload;
    }
}
