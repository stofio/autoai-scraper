<?php 
class ScrapaWebsite {

	// Constructor
	public function __construct() {
		require_once 'hQuery/hquery.php';
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

            // Load the ARTICLE page
            $articleDoc = hQuery::fromUrl($articleUrl, ['Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.8']);
            if (!$articleDoc) {
                throw new Exception("Failed to load article content from URL: $articleUrl");
            }

            //check if is NEWS
            $newsLabel = $articleDoc->find($selectors['newsLabelSel'])->text();
            if (strpos($newsLabel, $selectors['newsLabelText']) === false) {
                throw new Exception("The article is not categorized as 'News'.");
            }

            // Extracting elements based on selectors
            $title = $articleDoc->find($selectors['title'])->text();
            $content = $articleDoc->find($selectors['content'])->html();

            //featured image
            $imageUrl = $articleDoc->find($selectors['imageUrl'])->attr('content');
            if (!isValidUrl($imageUrl)) {
			    // If not valid, try getting the URL from 'src' attribute
			    $imageUrl = $articleDoc->find($selectors['imageUrl'])->attr('src');
			}

            $imageCredit = $config['defaultImageCredit'] ?? "";


            $cleanedContent = $this->cleanHtmlContent($content); // Method to clean the HTML content
            $simplifiedContent = $this->simplifyImageTags($cleanedContent); //get images url, and replace non jpg,png with span
           // $recleanedContent = $this->cleanHtmlContent($content); // redo the cleaning to remove emtpy span


            return [
                "title" => $title,
                "content" => $simplifiedContent,
                "img-url" => $imageUrl,
                "img-credit" => $imageCredit,
                "original-url" => $articleUrl
            ];

        } catch (Exception $e) {
            if ($e->getMessage() == "The article is not categorized as 'News'.") {
                my_second_log("INFO", "Skipped non-news article at " . $articleUrl);
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


	private function randomDelay() {
    	$delay = mt_rand(2000, 3000); // Generate a random delay between 1000ms (1s) and 3000ms (3s)
    	usleep($delay * 1000); // Convert milliseconds to microseconds for usleep function
	}


	private function cleanHtmlContent($content) {
	    $dom = new DOMDocument;
	    libxml_use_internal_errors(true);
	    $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
	    libxml_clear_errors();

	    $xpath = new DOMXPath($dom);

	    // Create a new DOMDocument for clean output
	    $cleanDom = new DOMDocument();

	    // Update the query to include h2, h3, h4, h5, and h6 tags
	    $nodes = $xpath->query('//text()[not(parent::script) and normalize-space()] | //img | //h2 | //h3 | //h4 | //h5 | //h6 | //ul | //ol | //figure');


	    $firstImage = true;
	    $textEncountered = false;

	    foreach ($nodes as $node) {
	        if ($node->nodeType === XML_TEXT_NODE) {
	            $textEncountered = true;
	            $cleanDom->appendChild($cleanDom->importNode($node, true));
	        } elseif ($node->nodeType === XML_ELEMENT_NODE && ($node->tagName === 'img' || $node->tagName === 'h2' || $node->tagName === 'h3' || $node->tagName === 'h4' || $node->tagName === 'h5' || $node->tagName === 'h6')) {
	            if ($node->tagName === 'img' && $firstImage && !$textEncountered) {
	                // Skip the first image if no text has been encountered
	                $firstImage = false;
	                continue;
	            }
	            $cleanDom->appendChild($cleanDom->importNode($node, true));
	            if ($node->tagName === 'img') {
	                $firstImage = false;
	            }
	        }
	    }

	    $cleaned_content = $cleanDom->saveHTML();
	    libxml_use_internal_errors(false);

	    // Output the cleaned content
	    return $cleaned_content;
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
	            if (preg_match('/\.(jpg|jpeg|png)(\?|&|$)/i', $attrValue)) {
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











}

?>