<?php

namespace App\CrawlBlog;

use Carbon\Carbon;
use Giauphan\CrawlBlog\Models\CategoryBlog;
use Giauphan\CrawlBlog\Models\Post;
use Giauphan\Goutte\GoutteFacade;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

class CrawlExample extends Command
{
    protected $signature = 'crawl:CrawlExample {url} {category_name} {lang} {limitblog}';
    protected $description = 'CrawlExample blog data from a given URL';

     public function handle()
    {
        $pageUrl = $this->argument('url');
        $categoryName = $this->argument('category_name');
        $lang = $this->argument('lang');
        $limit = $this->argument('limitblog');
    
        $category = CategoryBlog::firstOrCreate(['name' => $categoryName], ['slug' => Str::slug($categoryName)]);
        $categoryId = $category->id;
    
        $totaltimes = 0;

        $category = CategoryBlog::firstOrCreate(['name' => $categoryName], ['slug' => Str::slug($categoryName)]);
        $categoryId = $category->id;
        do {
            $crawler = GoutteFacade::request('GET', $pageUrl);

            $crawl_arr = $crawler->filter('.classnameBLog');
            if ($crawl_arr->count() === 0) {
                $this->error('No matching elements found on the page. Check if the HTML structure has changed.');
                break;
            }

            foreach ($crawl_arr as $node) {
                
                if ($node instanceof \DOMElement) {
                    $node = new Crawler($node);
                }

                $title = $node->filter('.Title')->text();
                $summary = $node->filter('.summary')->text();
                $image = optional($node->filter('.ImagesBlog')->first())->attr('data-lazy-src');
                $linkHref = $node->filter('.LinkBlog')->attr('href');
                $this->scrapeData($linkHref, $title, $image, $summary, $categoryId, $lang);

                $totaltimes++;
    
                if ($totaltimes >= $limit) {
                    $this->info('Reached the limit.');
                    break 2; 
                }
            };
            
            $nextLink = $crawler->filter('nav.pagination li a.next')->first();
            if ($nextLink->count()  <= 0) {
                break;
            }
            $nextPageUrl = $nextLink->attr('href');
            $pageUrl = $nextPageUrl;
        } while ($pageUrl !== '');
    }

    public function scrapeData($url, $title, $image, $summary, $categoryId, $lang)
    {
        $crawler = GoutteFacade::request('GET', $url);
        $content = $this->crawlData_html('#main .post', $crawler);
        $check = Post::all();

        if ($check->isEmpty()) {
            $this->createPost($title, $image, $summary, $content, $categoryId, $lang);
        } else {
            $this->checkAndUpdatePost($title, $image, $summary, $content, $check, $categoryId, $lang);
        }
    }

    protected function createPost($title, $image, $summary, $content, $categoryId, $lang)
    {
        $cleanedTitle = Str::slug($title, '-');
        $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $cleanedTitle);
        $dataPost = [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'images' => $image,
            'lang' => $lang,
            'published_at' => Carbon::now(),
            'summary' => $summary,
            'category_blog_id' => $categoryId,
            'SimilarityPercentage' => 0.0,
        ];
        Post::create($dataPost);
    }

    protected function checkAndUpdatePost($title, $image, $summary, $content, $check, $categoryId, $lang)
    {
        $checkTile = false;
        $similarityPercentage = 0.0;

        foreach ($check as $blog) {
            if ($blog->title !== $title) {
                $blog1Words = explode(' ', $blog->content);
                $blog2Words = explode(' ', $content);
                $commonWords = array_intersect($blog1Words, $blog2Words);
                $similarityPercentage += count($commonWords) / count($blog1Words);
            } else {
                $checkTile = true;
            }
        }

        if (!$checkTile && $title != null) {
            $similarityPercentage = $similarityPercentage / $check->count();
            $cleanedTitle = Str::slug($title, '-');
            $slug = preg_replace('/[^A-Za-z0-9\-]/', '', $cleanedTitle) . '.html';
            $dataPost = [
                'title' => $title,
                'slug' => $slug,
                'content' => $content,
                'images' => $image,
                'lang' => $lang,
                'published_at' => Carbon::now(),
                'summary' => $summary,
                'category_blog_id' => $categoryId,
                'SimilarityPercentage' => round($similarityPercentage, 2),
            ];
            Post::create($dataPost);
        }
    }

    protected function crawlData(string $type, $crawler)
    {
        $result = $crawler->filter($type)->first();

        return $result ? $result->text() : '';
    }

    protected function crawlData_html(string $type, $crawler)
    {
        $nodeList = $crawler->filter($type);
        if ($nodeList->count() === 0) {
            return '';
        }

        $result = $nodeList->first();
        return $result ? $result->html() : '';
    }
}
