<?php
//this class scrapes the source web page by given settings
//cleans the html elements

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


header('Content-Type: text/html; charset=utf-8');
use Symfony\Component\DomCrawler\Crawler;

class ScrapaWebsite {

    // Constructor
    public function __construct() {
        require_once plugin_dir_path(__DIR__) . '../hQuery/hquery.php';
        require_once plugin_dir_path(__DIR__) . '../includes/utilities.php';
    }

    /** example config array
     $digitaltrendsMobileConfig = [
        'baseUrl' => 'https://www.digitaltrends.com/mobile-news/',
        'selectors' => [
            'catPageLastArticle' => '.b-mem__post--xl',
            'title' => 'header#dt-post-title h1',
            'content' => '#dt-post-content',
            'imageUrl' => 'meta[property="og:image"]',
            'typeArticle' => '.b-headline__top li a span',
            'typeArticleText' => 'News'
        ],
        'defaultImageCredit' => 'Digital Trends'
    ];
     **/

    public function scrapeWebsite($sourceSettings, $articleUrl) { //
        try {

            $url = $sourceSettings['baseUrl'];
            $selectors = $sourceSettings['selectors'];

             my_second_log('INFO', 'Getting article: ' . $articleUrl);

            // Load the ARTICLE page
            $articleDoc = hQuery::fromUrl($articleUrl, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
            if (!$articleDoc) {
                throw new Exception("Failed to load article content from URL: $articleUrl");
            }
    
    
            //check article type
            if($selectors['typeArticle'] && $selectors['typeArticleText']) {
                if($articleDoc->find($selectors['typeArticle']) == null) {
                    throw new Exception("Not of article type.");
                }
                $newsLabel = $articleDoc->find($selectors['typeArticle'])->text();
                if (strpos($newsLabel, $selectors['typeArticleText']) === false) {
                    throw new Exception("Not of article type.");
                }
            }
            
            if($articleDoc->find($selectors['title'])) {
                $title = $articleDoc->find($selectors['title'])->text();
            }
            else {
                throw new Exception("Error with title of scraped article.");
            }

            if($articleDoc->find($selectors['content'])) {
                $content = $articleDoc->find($selectors['content'])->html();
            }
            else {
                throw new Exception("Error with content of scraped article.");
            }

            //featured image
            if($articleDoc->find($selectors['imageUrl'])) {
                $imageUrl = $articleDoc->find($selectors['imageUrl'])->attr('content');
                if (!isValidUrl($imageUrl)) {
                    // If not valid, try getting the URL from 'src' attribute
                    $imageUrl = $articleDoc->find($selectors['imageUrl'])->attr('src');
                }
            }
            
            $imageCredit = $sourceSettings['defaultImageCredit'] ?? "";

            //clean scraped content
            $encodedContent = mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8');
            $contentFirstClean = $this->removeScriptTags($this->removeATags($encodedContent));
            $content1 = $this->cleanHtmlContent($contentFirstClean); // Method to clean the HTML content
            $content2 = $this->removeSpecificTags($content1);
            $content3 = $this->simplifyImg($content2);
            $content4 = $this->reorderAndExtractImages($content3);
            $content5 = $this->removeHtmlComments($content4);
            $content6 = $this->checkImgsInclusion($content5, $sourceSettings['getImages']);
            $content7 = $this->checkTablesInclusion($content6, $sourceSettings['getTables']);
            $content8 = $this->removeEmptyElements($content7);
            $content9 = $this->removeFirstImage($content8, $sourceSettings['excludeFirstImage']);

            

            
            return [
                "title" => $title,
                "content" => $content9,
                "img-url" => $imageUrl,
                "img-credit" => $imageCredit,
                "original-url" => $articleUrl
            ];

        } catch (Exception $e) {
            if ($e->getMessage() == "The article is not categorized as 'News'.") {
                my_second_log("INFO", "Skipped, not article type text: " . $articleUrl);
                return null;
            } else {
                my_second_log("ERROR", "Error in scrapeWebsite: " . $e->getMessage());
                return "error";
            }
        }
    }

    public function scrapeCategoryPage($catPageUrl, $listContainer, $firstArticleHref) {
        // Initial request to get the list of articles or the CATEGORY page
        $doc = hQuery::fromUrl($catPageUrl, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
        if (!$doc) {
            throw new Exception("Failed to load content from URL: $catPageUrl");
        }
        
        // Find the container element
        $container = $doc->find($listContainer);

        // Extract article URLs from within that container 
        $articleUrls = [];
        foreach($container->find($firstArticleHref) as $link) {
            $articleUrls[] = $link->href;
        }

        return $articleUrls;      
    }

    private function checkImgsInclusion($content, $isGetImages) {
        if($isGetImages) return $content;
        //remove images if needed
        if($isGetImages == false) {
            //REMOVE
            $pattern = '/<img[^>]*>/';
            $content_without_images = preg_replace($pattern, '', $content);
            return $content_without_images;
        }
        else {
            return $content;
        }
   }

    private function checkTablesInclusion($content, $isGetTables) {
        if($isGetTables) {
            return $content; 
        }
    
        // Remove tables if $isGetTables is false
        $pattern = '/<table[^>]*>(.*?)<\/table>/is'; 
        $contentWithoutTables = preg_replace($pattern, '', $content);
    
        return $contentWithoutTables;
    }
 
    private function isUrlScraped($url) {
        $scrapedUrlsFile = dirname(plugin_dir_path(__FILE__)) . '/scraped_urls.json'; // Update path as needed
        if (!file_exists($scrapedUrlsFile)) {
            return false;
        }

        $json = file_get_contents($scrapedUrlsFile);
        $scrapedUrls = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Error reading scraped URLs file.");
        }

        return in_array($url, $scrapedUrls);
    }

    private function reorderContentChilds($htmlContent) {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $flatDom = new DOMDocument();
        $this->flattenNodes($dom->documentElement, $flatDom);

        return $flatDom->saveHTML();
    }

    //make all nested nodes at the same first level
    private function flattenNodes(DOMNode $node, DOMDocument $flatDom) {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                // Clone the element node to avoid modifying the original structure
                $clonedNode = $child->cloneNode(false); // false to not clone children
                // Append the cloned node to the flat DOM
                $flatDom->appendChild($flatDom->importNode($clonedNode, true));

                // Recursively process child nodes
                $this->flattenNodes($child, $flatDom);
            } elseif ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) != '') {
                // Create a new `p` element for the text node
                $paragraph = $flatDom->createElement('p');
                $paragraph->appendChild($flatDom->importNode($child, true));
                // Append the `p` element to the flat DOM
                $flatDom->appendChild($paragraph);
            }
        }
    }

    private function cleanHtmlContent($htmlString) {
    	$tagsToKeep = ['p', 'h1', 'h2', 'h3', 'ul', 'ol', 'li', 'table', 'tr', 'td', 'th', 'img'];

    	$crawler = new Crawler($htmlString);

	    // Remove all tags except the allowed ones
        $crawler->filter('*')->each(function (Crawler $node, $i) use ($tagsToKeep) {
            if (!in_array($node->nodeName(), $tagsToKeep)) {
                // Replace the current node with its own content
                $html = '';
                foreach ($node->children() as $child) {
                    $html .= $child->ownerDocument->saveHTML($child);
                }
                $node->getNode(0)->parentNode->replaceChild(new DOMText($html), $node->getNode(0));
            }
        });

        // Return cleaned HTML
        return html_entity_decode($crawler->html());
    }

    //remove attributes and links, except for images
    private function removeHtmlAttributes($html) {
        // Create a new DOMDocument and load the HTML content
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress loadHTML warnings
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Iterate over all elements
        foreach ($dom->getElementsByTagName('*') as $element) {
            // Skip <img> tags
            if (strtolower($element->tagName) === 'img') {
                continue;
            }

            // Remove attributes from all other elements
            while ($element->attributes->length > 0) {
                $element->removeAttribute($element->attributes->item(0)->name);
            }
        }

        // Return the cleaned HTML
        return $dom->saveHTML();
    }

    private function simplifyImageTags($content) {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();


        $images = $dom->getElementsByTagName('img');

        // Collect images into an array
        $imageArray = [];
        foreach ($images as $image) {
            $imageArray[] = $image;
        }

        // Process each image
        foreach ($imageArray as $key => $image) {
            $imageSrc = null;
            $newImg = null;
            
            // Loop through each attribute of the image
            foreach ($image->attributes as $attribute) {
                $attrValue = $attribute->value;

                // Check if the attribute value contains a valid URL with jpg, jpeg, png or webp extension
                if (preg_match('/\.(jpg|jpeg|png|webp)(\?|&|$)/i', $attrValue)) {
                    $imageSrc = $attrValue;
                    break; // Break the loop if a valid URL is found
                }
            }

            // Create a new simplified image tag
            if ($imageSrc) {
                $newImg = $dom->createElement('img');
                $newImg->setAttribute('src', $imageSrc);
            } else {
                $newImg = $dom->createElement('span');
            }

            // Replace the old image tag with the new one
            $image->parentNode->replaceChild($newImg, $image);
        }

        $simplifiedContent = $dom->saveHTML();
        libxml_use_internal_errors(false);
        return $simplifiedContent;
    }

    private function simplifyImg($htmlString) {
        // Create a Crawler instance
        $crawler = new Crawler($htmlString);
    
        // Find all image tags
        $images = $crawler->filter('img');
    
        // Iterate over all images
        foreach ($images as $img) {
            // Check each attribute
            $value = $img->getAttribute('src');

            my_log($value);
    
            // Check if the attribute value is a valid URL with specific extensions
            if (preg_match('/\.(jpg|jpeg|png|webp)(\?.*)?$/i', $value)) {
                // Create a new image tag with only the src attribute
                $newImg = '<img src="' . $value . '">';
    
                // Create a new Crawler instance for the new image tag
                $newCrawler = new Crawler($newImg);
    
                // Replace the old image tag with the new one
                $img->parentNode->replaceChild(
                    $img->ownerDocument->importNode($newCrawler->getNode(0), true),
                    $img
                );
            }
        }
    
        return $crawler->html();
    }

    private function reorderAndExtractImages($content) {
        $content = mb_convert_encoding('<?xml encoding="UTF-8">' . $content, 'UTF-8', 'auto');
        $dom = new DOMDocument();
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $dom->encoding = 'UTF-8';

        $newDom = new DOMDocument();
        $body = $newDom->createElement('body');
        $newDom->appendChild($body);
        
        //recursively
        $processNode = function($node) use ($newDom, $body, &$processNode) {
            foreach ($node->childNodes as $child) {
                
                if ($child instanceof DOMElement || $child instanceof DOMText) {
                    if ($child->tagName === 'p' || $child->nodeName === '#text') {
                        // Clone the <p> tag
                        if($child->tagName === 'p') {
                            $newP = $newDom->importNode($child, false);
                            $body->appendChild($newP);
                        }
                        else { //if its only text
                            $newP = $newDom->createElement('p');
                            $newText = $newDom->createTextNode($child->wholeText);
                            $newP->appendChild($newText);
                            $body->appendChild($newP);
                        }

                        // Check for and move <img> tags
                        foreach ($child->childNodes as $pChild) {
                            
                            if ($pChild instanceof DOMElement && $pChild->tagName === 'img') {
                                $newImg = $newDom->importNode($pChild, true);
                                $body->appendChild($newImg);
                            } else {
                                // Clone non-<img> nodes inside <p>
                                $newP->appendChild($newDom->importNode($pChild, true));
                            }
                        }
                    } elseif (in_array($child->tagName, ['img', 'ul', 'ol', 'table', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                        // Directly clone other allowed tags
                        $newNode = $newDom->importNode($child, true);
                        $body->appendChild($newNode);
                    }
                } elseif ($child->hasChildNodes()) {
                    // Recurse into child nodes
                    $processNode($child);
                }
            }
        };


        // start recursive funct
        $processNode($dom->documentElement);

        //remove body tag
        $dom->encoding = 'UTF-8';
        $htmlString = $newDom->saveHTML($body);
        $htmlString = str_replace(array('<body>', '</body>'), '', $htmlString);

        // Decode HTML entities
       // $htmlString = html_entity_decode($htmlString, ENT_QUOTES, 'UTF-8');
        // Remove strange characters
        //$htmlString = preg_replace('/&#x[a-fA-F0-9]{2,};/', '', $htmlString);

        return mb_convert_encoding($htmlString, 'UTF-8', 'HTML-ENTITIES');
    }



    private function removeScriptTags($htmlString) {
        $htmlString = mb_convert_encoding($htmlString, 'UTF-8', 'auto');
        // Regular expression to match <script> tags and their content
        $pattern = '/<script\b[^>]*>(.*?)<\/script>/is';
        // Replace matched script tags with an empty string
        $cleanHtml = preg_replace($pattern, '', $htmlString);
        return $cleanHtml;
    }

    private function removeHtmlComments($htmlString) {
        // Convert encoding to UTF-8 to ensure proper handling of all characters
        $htmlString = mb_convert_encoding($htmlString, 'UTF-8', 'auto');
        // Regular expression to match HTML comments
        $pattern = '/<!--.*?-->/s';
        // Replace matched HTML comments with an empty string
        $cleanHtml = preg_replace($pattern, '', $htmlString);
        return $cleanHtml;
    }

    private function removeATags($htmlContent) {

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($htmlContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $aTags = $dom->getElementsByTagName('a');

        for ($i = $aTags->length - 1; $i >= 0; $i--) {
            $aTag = $aTags->item($i);
            
            $parentNode = $aTag->parentNode;

            // Move all children of <a> to its parent node
            while ($aTag->childNodes->length > 0) {
                $child = $aTag->childNodes->item(0);
                $parentNode->insertBefore($child, $aTag);
            }

            // Remove the now-empty <a> tag
            $parentNode->removeChild($aTag);
        }

        return $dom->saveHTML();
    }


    private function removeEmptyElements($htmlString) {
        $htmlString = mb_convert_encoding('<?xml encoding="UTF-8">' . $htmlString, 'UTF-8', 'auto');
        $dom = new DOMDocument();

        @$dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $emptyPTags = $dom->getElementsByTagName('p');

        for($i = $emptyPTags->length - 1; $i >= 0; $i--) {
            $pTag = $emptyPTags->item($i);
            if(trim($pTag->textContent) === '') {
                $pTag->parentNode->removeChild($pTag); 
            }
        }

        $brTags = $dom->getElementsByTagName('br');
        for($i = $brTags->length - 1; $i >= 0; $i--) {
            $brTag = $brTags->item($i);
            $brTag->parentNode->removeChild($brTag);
        }

        $html = $dom->saveHTML();

        // Remove "<?xml encoding="UTF-8">" from the beginning of the string
        $xmlDeclaration = '<?xml encoding="UTF-8">';
        if (strpos($html, $xmlDeclaration) === 0) {
            $html = substr($html, strlen($xmlDeclaration));
        }

        return mb_convert_encoding($html, 'UTF-8', 'HTML-ENTITIES');

    }

    private function removeFirstImage($htmlString, $toExclude) {
        $htmlString = mb_convert_encoding('<?xml encoding="UTF-8">' . $htmlString, 'UTF-8', 'auto');
        $dom = new DOMDocument();
        @$dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      
        $firstElement = $dom->getElementsByTagName('*')->item(0);
      
        if ($firstElement instanceof DOMElement && 
            $firstElement->tagName == 'img' && $toExclude) {
      
            $firstElement->parentNode->removeChild($firstElement);
        }

        $html = $dom->saveHTML();

        // Remove "<?xml encoding="UTF-8">" from the beginning of the string
        $xmlDeclaration = '<?xml encoding="UTF-8">';
        if (strpos($html, $xmlDeclaration) === 0) {
            $html = substr($html, strlen($xmlDeclaration));
        }

        return mb_convert_encoding($html, 'UTF-8', 'HTML-ENTITIES');
      
    }

	private function removeSpecificTags($htmlString) {
	    $tagsToRemove = ['figure', 'div', 'picture', 'source', 'figcaption', 'span', 'aside', 'meta', 'br'];
	    foreach ($tagsToRemove as $tag) {
	        $htmlString = preg_replace('/<'.$tag.'[^>]*>/', '', $htmlString);
	        $htmlString = preg_replace('/<\/'.$tag.'>/', '', $htmlString);
	    }
	    return $htmlString;
	}




	

}


?>