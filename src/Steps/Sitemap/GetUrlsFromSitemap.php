<?php

namespace Crwlr\Crawler\Steps\Sitemap;

use Crwlr\Crawler\Steps\Step;
use Generator;
use Symfony\Component\DomCrawler\Crawler;

class GetUrlsFromSitemap extends Step
{
    protected bool $withData = false;

    public function withData(): static
    {
        $this->withData = true;

        return $this;
    }

    /**
     * @param Crawler $input
     */
    protected function invoke(mixed $input): Generator
    {
        foreach ($input->filter('urlset url') as $urlNode) {
            $urlNode = new Crawler($urlNode);

            if ($urlNode->children('loc')->first()->count() > 0) {
                if ($this->withData) {
                    yield $this->getWithAdditionalData($urlNode);
                } else {
                    yield $urlNode->children('loc')->first()->text();
                }
            }
        }
    }

    protected function validateAndSanitizeInput(mixed $input): mixed
    {
        return $this->validateAndSanitizeToDomCrawlerInstance($input);
    }

    /**
     * @return string[]
     */
    protected function getWithAdditionalData(Crawler $urlNode): array
    {
        $data = ['url' => $urlNode->children('loc')->first()->text()];

        $properties = ['lastmod', 'changefreq', 'priority'];

        foreach ($properties as $property) {
            $node = $urlNode->children($property)->first();

            if ($node->count() > 0) {
                $data[$property] = $node->text();
            }
        }

        return $data;
    }
}
