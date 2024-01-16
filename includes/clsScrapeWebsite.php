<?php 
class ScrapaWebsite {

	// Constructor
	public function __construct() {
		require_once plugin_dir_path(__DIR__) . '/hQuery/hquery.php';
	}

	/** example config array
	 $digitaltrendsMobileConfig = [
        'baseUrl' => 'https://www.digitaltrends.com/mobile-news/',
        'selectors' => [
            'catPageLastArticle' => '.b-mem__post--xl',
            'title' => 'header#dt-post-title h1',
            'content' => '#dt-post-content',
            'imageUrl' => 'meta[property="og:image"]',
            'newsLabelSel' => '.b-headline__top li a span',
            'newsLabelText' => 'News'
        ],
        'defaultImageCredit' => 'Digital Trends'
    ];
	 **/
    public function scrapeWebsite($config, $pageUrl = null) { //
        try {

            $url = $config['baseUrl'];
            $selectors = $config['selectors'];

            if(!$pageUrl) {

	            // Initial request to get the list of articles or the CATEGORY page
	            $doc = hQuery::fromUrl($url, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
	            if (!$doc) {
	                throw new Exception("Failed to load content from URL: $url");
	            }

	            $catPageLastArticle = $doc->find($selectors['catPageLastArticle']);

	            if (!$catPageLastArticle) {
		            throw new Exception("Category page last article not found.");
		        }

	            $articleUrl = $config['directUrl'] ?? $catPageLastArticle->find('a')->attr('href');

	            // Validate URL
		        if (!filter_var($articleUrl, FILTER_VALIDATE_URL)) {
		            throw new Exception("Invalid article URL: $articleUrl");
		        }

		        // Check if URL is already scraped
	            if ($this->isUrlScraped($articleUrl)) {
	                my_second_log("INFO", "URL already scraped: " . $articleUrl);
	                return null;
	            }
	        }
	        else {
	        	// Validate URL
		        if (!filter_var($pageUrl, FILTER_VALIDATE_URL)) {
		            throw new Exception("Invalid article URL: $pageUrl");
		        }
	        	$articleUrl = $pageUrl;
	        }

	         my_second_log('INFO', 'Getting article: ' . $articleUrl);

            // Load the ARTICLE page
            $articleDoc = hQuery::fromUrl($articleUrl, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
            if (!$articleDoc) {
                throw new Exception("Failed to load article content from URL: $articleUrl");
            }
    
    
            //check if is NEWS
			if($selectors['newsLabelSel'] && $selectors['newsLabelText']) {
				if($articleDoc->find($selectors['newsLabelSel']) == null) {
					throw new Exception("The article is not categorized as 'News'.");
				}
				$newsLabel = $articleDoc->find($selectors['newsLabelSel'])->text();
				if (strpos($newsLabel, $selectors['newsLabelText']) === false) {
					throw new Exception("The article is not categorized as 'News'.");
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

            $imageCredit = $config['defaultImageCredit'] ?? "";


            //clean scraped content
          	$content1 = $this->reorderContentChilds($content);
            $content2 = $this->cleanHtmlContent($content1); // Method to clean the HTML content
            $content3 = $this->removeHtmlAttributes($content2); // remove attributes from all tags
            $content4 = $this->simplifyImageTags($content3); //get images url, and replace non jpg,png with span
            $content5 = $this->reorderAndExtractImages($content4); //check if img is still nested, bring as first child 


            return [
                "title" => $title,
                "content" => $content4,
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


	private function reorderContentChilds($content) {
   	    // Load the content into a DOMDocument
   	    $dom = new DOMDocument();
   	    @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

   	    // Create a new DOMDocument to store reordered content
   	    $newDom = new DOMDocument();
   	    $body = $newDom->createElement('body');
   	    $newDom->appendChild($body);

   	    // Define the tags to keep
   	    $tagsToKeep = ['p', 'img', 'ul', 'ol', 'table', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];

   	    // Function to recursively move allowed tags to the new DOM
   	    $moveTags = function($node) use ($newDom, $body, $tagsToKeep, &$moveTags) {
   	        foreach ($node->childNodes as $child) {
   	            if ($child instanceof DOMElement && in_array($child->tagName, $tagsToKeep)) {
   	                // Clone the node and append it to the body of new DOM
   	                $newNode = $newDom->importNode($child, true);
   	                $body->appendChild($newNode);
   	            } elseif ($child->hasChildNodes()) {
   	                // Recurse into child nodes
   	                $moveTags($child);
   	            }
   	        }
   	    };

   	    // Start the recursive process
   	    $moveTags($dom->documentElement);

   	    // Return the HTML from the new DOM
   	    return $newDom->saveHTML($body);
   	}


	private function cleanHtmlContent($html) {
	    // Create a new DOMDocument and load the HTML content
	    $dom = new DOMDocument();
	    libxml_use_internal_errors(true);
	    $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	    libxml_clear_errors();

	    // Create a new DOMDocument to hold the clean content
	    $cleanDom = new DOMDocument();
	    foreach ($dom->documentElement->childNodes as $node) {
	        // Check if the node is one of the allowed tags
	        if ($node instanceof DOMElement && in_array($node->nodeName, ['p', 'img', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table'])) {
	            // Import the node (and its children) to the clean DOMDocument
	            $importedNode = $cleanDom->importNode($node, true);
	            $cleanDom->appendChild($importedNode);
	        }
	    }

	    // Return the cleaned HTML
	    return $cleanDom->saveHTML();
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

	            // Check if the attribute value contains a valid URL with jpg, jpeg, or png extension
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


	private function reorderAndExtractImages($content) {
	    $dom = new DOMDocument();
	    @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

	    $newDom = new DOMDocument();
	    $body = $newDom->createElement('body');
	    $newDom->appendChild($body);

	    $processNode = function($node) use ($newDom, $body, &$processNode) {
	        foreach ($node->childNodes as $child) {
	            if ($child instanceof DOMElement) {
	                if ($child->tagName === 'p') {
	                    // Clone the <p> tag
	                    $newP = $newDom->importNode($child, false);
	                    $body->appendChild($newP);

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

	    $processNode($dom->documentElement);

	    return $newDom->saveHTML($body);
	}












}
?>