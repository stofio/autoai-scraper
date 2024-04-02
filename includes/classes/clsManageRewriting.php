<?php
//this class prepares the scraped and cleaned content for rewriting
//by splitting the content logically into chunks, and sending it to the AI rewriting class

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__DIR__) . '/classes/clsAiRewriting.php';
use Symfony\Component\DomCrawler\Crawler;


class clsManageRewriting {

    private $apiKey;
    private $rewriter;

    public function __construct() {
        $this->apiKey = get_option('open_ai_key_option');
        $this->rewriter = new OpenAIRewriting();
    }

    public function generateContentWithAI($scrapedArticle, $source) {
        $contentInChunks = $this->divideScrapedContentByChunks($scrapedArticle['content'], $source['_content_fetcher_splitting_words']);

        $titleAndExcerpt = $this->rewriter->getRewrittenTitleAndExcerpt(["content" => $contentInChunks[0], "title" => $scrapedArticle['title']], $source['_content_fetcher_title_excerpt'][0]);
        if ($titleAndExcerpt === 'error') {
            return 'error';
        }

        $rewrittenChunks = [];
        foreach ($contentInChunks as $chunk) {
            if (strpos($chunk, '<table') !== false) {
                $rewrTable = $this->rewriter->rewriteTable($chunk, $titleAndExcerpt['title'], $source['_content_fetcher_table'][0]);
                array_push($rewrittenChunks, $rewrTable);
            } elseif (strpos($chunk, '<img') !== false) {
                $imageString = $this->extractFirstImageTag($chunk);
                $chunkWithPlaceholder = $this->addPlaceholderForImage($chunk);
                $rewrWithPlaceholder = $this->rewriter->getRewrittenArticlePieceWithImage($chunkWithPlaceholder, $titleAndExcerpt['title'], $source['_content_fetcher_piece_article'][0]);
                $rewrWithImage = $this->reinsertImageOnPlaceholder($rewrWithPlaceholder, $imageString);
                array_push($rewrittenChunks, $rewrWithImage);
            } else {
                $rewrWithoutImage = $this->rewriter->getRewrittenArticlePiece($chunk, $titleAndExcerpt['title'], $source['_content_fetcher_piece_article'][0]);
                array_push($rewrittenChunks, $rewrWithoutImage);
            }
        }

        $finalRewrittenContent = implode('', $rewrittenChunks);

        return [
            'title' => $titleAndExcerpt['title'],
            'excerpt' => $titleAndExcerpt['excerpt'],
            'content' => $finalRewrittenContent,
            'img-url' => $scrapedArticle['img-url'],
            'img-credit' => $scrapedArticle['img-credit']
        ];
    }

    private function extractFirstImageTag($contentPiece) {
        $pattern = '/<img[^>]+>/i';
        if (preg_match($pattern, $contentPiece, $matches)) {
            return $matches[0];
        } else {
            return '';
        }
    }

    private function addPlaceholderForImage($contentPiece) {
        $pattern = '/<img[^>]+>/i';
        $replacement = '[IMG_PLACEHOLDER]';
        $result = preg_replace($pattern, $replacement, $contentPiece);
        return $result;
    }

    private function reinsertImageOnPlaceholder($rewrittenContentPiece, $imageString) {
        $placeholder = '[IMG_PLACEHOLDER]';
        $result = str_replace($placeholder, $imageString, $rewrittenContentPiece);
        return $result;
    }

    private function divideScrapedContentByChunks($content, $wordLimit) {
        $encodedContent = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
        $dom = new DOMDocument();
        @$dom->loadHTML($encodedContent);
        
        
        $chunks = $this->createChunks($dom, $wordLimit);
        $chunks2 = $this->extractTables($chunks);
        $chunks3 = $this->splitChunksByImages($chunks2);

        return $chunks3;
    }

    /**
     * Divides the content of a DOM document into chunks based on a specified word limit.
     */
    private function createChunks($dom, $wordLimit) {
        $body = $dom->getElementsByTagName('body')->item(0);
        $childNodes = $body->childNodes;
        $chunks = [];
        $currentChunk = '';
        $currentWordCount = 0;

        foreach ($childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) continue; // Skip non-element nodes

            $text = $node->textContent;
            $wordCount = str_word_count($text);
            $nextNode = $node->nextSibling;

            // Check if the current node should be grouped with the next
            if ($this->isContextuallyRelated($node, $nextNode, $currentWordCount, $wordLimit)) {
                // Add to current chunk
                $currentChunk .= $dom->saveHTML($node);
                $currentWordCount += $wordCount;
            } else {
                // Check if adding this node exceeds word limit
                if ($currentWordCount + $wordCount > $wordLimit) {
                    // Special handling for headings
                    if ($this->isHeading($node) && $nextNode !== null) {
                        // If it's a heading, defer adding it until the next chunk
                        $chunks[] = $currentChunk;
                        $currentChunk = $dom->saveHTML($node);
                        $currentWordCount = $wordCount;
                    } else {
                        // Current chunk is complete, start a new chunk
                        $currentChunk .= $dom->saveHTML($node);
                        $chunks[] = $currentChunk;
                        $currentChunk = '';
                        $currentWordCount = 0;
                    }
                } else {
                    // Add to current chunk
                    $currentChunk .= $dom->saveHTML($node);
                    $currentWordCount += $wordCount;
                }
            }

            
        }

        // Add the remaining content as the last chunk
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Extracts tables from each chunk and places them into separate chunks.
     */
    private function extractTables($chunks) {
        $chunksWithTables = [];

        foreach ($chunks as $chunk) {
            // Use DOMDocument to parse each chunk
            $chunkDom = new DOMDocument();
            // Load the HTML of the current chunk
            @$chunkDom->loadHTML($chunk);
            // Get all table elements in the current chunk
            $tables = $chunkDom->getElementsByTagName('table');

            // If tables are found in the chunk, extract and add them to a new chunk
            if ($tables->length > 0) {
                // Initialize a new chunk to store extracted tables
                $tableChunk = '';

                // Iterate over tables and append them to the table chunk
                foreach ($tables as $table) {
                    // Append the HTML of the current table to the table chunk
                    $tableChunk .= $chunkDom->saveHTML($table);
                    // Remove the table from the current chunk
                    $table->parentNode->removeChild($table);
                }

                // Add the table chunk to the result array
                $chunksWithTables[] = $tableChunk;
            }

            // Remove the HTML, body, and doctype tags from the remaining content of the chunk
            $htmlWithoutTags = '';
            $body = $chunkDom->getElementsByTagName('body')->item(0);
            if ($body) {
                // Get the inner HTML of the body element
                foreach ($body->childNodes as $node) {
                    $htmlWithoutTags .= $chunkDom->saveHTML($node);
                }
            }
            // Add the remaining content of the chunk (without HTML, body, and doctype tags) to the result array
            $chunksWithTables[] = $htmlWithoutTags;
        }

        return $chunksWithTables;
    }

    /**
     * Splits chunks containing multiple images so that each chunk contains only one image.
    */
    private function splitChunksByImages($pieces) {
        $newPieces = [];

        
        foreach ($pieces as $piece) {
            $dom = new DOMDocument();
            $dom->loadHTML($piece);
            
            $images = $dom->getElementsByTagName('img');

            $table = $dom->getElementsByTagName('table');
            if($table->length > 0) {
                $newPieces[] = $piece;
                continue;
            }

            //split each item 1 image
            if ($images->length >= 2) {
                $prevHTML = ''; // Variable to store HTML before the first image
                $foundFirstImage = false;
                
                foreach($this->splitHTMLStringByImages($piece) as $slicedPart) {
                    $newPieces[] = $slicedPart;
                }

            } else {
                // If there are less than 2 images, keep the piece as is
                $newPieces[] = $piece;
            }
        }
        return $newPieces;
    }
    //return array of chunks
    private function splitHTMLStringByImages($html) {
        // Create a new Crawler instance
        $crawler = new Crawler($html);
        
        // Array to hold the split HTML parts
        $parts = [];
        
        // Track the previous position of an image to slice the HTML correctly
        $previousPosition = 0;
        
        // Find all images in the HTML
        $images = $crawler->filter('img');
        
        // If no images are found, return the original HTML as a single part
        if ($images->count() == 0) {
            return [$html];
        }
        
        // Iterate over each image to split the HTML
        foreach ($images as $image) {
            // Get the position of the current image in the original HTML
            $position = strpos($html, $crawler->getNode(0)->ownerDocument->saveHTML($image));
            
            // Slice the HTML from the previous image to the current one
            $part = substr($html, $previousPosition, $position - $previousPosition);
            
            // Add the sliced part to the result array
            if (!empty(trim($part))) {
                $parts[] = $part;
            }
            
            // Update the previous position for the next iteration
            $previousPosition = $position;
        }
        
        // Add the remaining HTML after the last image
        $parts[] = substr($html, $previousPosition);
        
        return $parts;
    }

    //check if element should be grouped with text
    private function isContextuallyRelated($currentNode, $nextNode, $currentWordCount, $wordLimit) {
        // Group sequential headings (like h2 following h1)
        if ($this->isHeading($currentNode) && $this->isHeading($nextNode)) {
            return true;
        }

        // Group a paragraph with a following list (ul, ol)
        if ($currentNode->nodeName === 'p' && ($nextNode->nodeName === 'ul' || $nextNode->nodeName === 'ol')) {
            return true;
        }

        // Group adjacent paragraphs
        if ($currentNode->nodeName === 'p' && $nextNode->nodeName === 'p') {
            return true;
        }

        // Group thematic breaks (hr) appropriately
        if ($currentNode->nodeName === 'hr' || $nextNode->nodeName === 'hr') {
            return false; // typically signifies a thematic or section break
        }

        // Group a table with its preceding paragraph
        if ($currentNode->nodeName === 'p' && $nextNode->nodeName === 'table') {
            return true;
        }

        // Contextual keywords check
        if ($this->containsContextualKeyword($currentNode->textContent)) {
            return true;
        }

        // Default to false to not force grouping
        return false;
    }

    private function isHeading($node) {
        return in_array($node->nodeName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
    }

    private function containsContextualKeyword($textContent) {
        $keywords = ['Furthermore', 'Additionally', 'On the other hand', 'Moreover', 'In conclusion', 'However'];
        foreach ($keywords as $keyword) {
            if (strpos($textContent, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
    

}
