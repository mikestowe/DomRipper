<?php
class DomRipper {
  function __construct() {
    $this->headers = array(
    'http'=>array(
      'header'=>"Accept-language: en\r\n" .
                "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13 ( .NET CLR 3.5.30729; .NET4.0E)\r\n" . 
                "Accept-charset: utf-8\r\n"
//                "Host: yourhost.com\r\n"
      )
    );
  }
  
  private function startup($url) {
  	$this->DOM = new DOMDocument;
    
  	$file_content = apc_fetch(md5($url),$archived);
    $redirect = apc_fetch(md5($url).'_redirect');
        
  	if(!$archived) {
  	  $context = stream_context_create($this->headers);
  		$file_content = @file_get_contents($url,false,$context);
      
      /* Test for Redirects to Create Correct Path */
      if(preg_match('/(301)/',$http_response_header[0])) {
        $redirect_locations = array();
        foreach($http_response_header as $rhvalue) {
          if(preg_match('/location:/i',$rhvalue)){
            $redirect_locations[] = $rhvalue;
          }
        }
        $redirect = str_replace('Location: ','',array_pop($redirect_locations));
      } else {
        $redirect = false;
      }
      /* End Redirect Test */
  		apc_add(md5($url),$file_content,APC_TIME_SAVE);
      apc_add(md5($url).'_redirect',$redirect,APC_TIME_SAVE);
  	}
    
    // Build Path & Current Folder
    $this->path = str_replace(BASE_URL,'',($redirect === false?$url:$redirect));
    $folders = explode('/',$this->path);
    array_pop($folders);
    $this->folder = implode('/',$folders).'/';
    
    $this->file_contents = $file_content;
  	$this->DOM->loadHTML($file_content);
  }
  
  
  function manual_startup($url) {
  	$this->DOM = new DOMDocument;
  	$this->DOM->loadHTMLFile($url);
  }
  
  function fetch($url,$identifier,$type='id',$element_number=1) {
  	$content = apc_fetch(md5($url).md5($identifier.$type).$element_number,$archived);
  	if($archived) {
  		return $content;
  	} elseif(!isset($this->DOM)) {
  		// DOM is not set, run startup
  		$this->startup($url);
  	}
  	
     // Get by Tag Name and Number
    if($type == 'tag') {
      $elements = $this->DOM->getElementsByTagName($identifier);
      $i = 1;
      foreach ($elements as $elem) { 
        if($i == $element_number) {
          break;
        } else {
          $i++;
        }
      } 
    // Get by ClassName 
    } elseif($type == 'class') {
      unset($this->DOM);
      $this->DOM = new DOMDocument;
      $this->DOM->loadHTML(str_replace('class="'.$identifier.'"', 'id="ripper_class_'.$identifier.'"', $this->file_contents));
      $elem = $this->DOM->getElementById('ripper_class_'.$identifier);
    // Get by Identifier
    } else {
      $elem = $this->DOM->getElementById($identifier);
    }
    
  	$return = $this->innerHTML($elem);
    if(empty($return)) { $return = NO_CONTENT; }
  	apc_add(md5($url).md5($identifier.$type).$element_number,$return,APC_TIME_SAVE);
  	return $return;
  }
  
  
  function innerHTML($elem) {
	  $innerHTML = '';

	  $children = $elem->childNodes;
	  foreach ($children as $child) {
	    $tmp_doc = new DOMDocument();
	    $tmp_doc->appendChild($tmp_doc->importNode($child,true));       
	    $innerHTML .= $tmp_doc->saveHTML();
	  }
	  
	  return $this->format($innerHTML);
  }
  
  
  function resize($src,$ratio) {
  	$return = array();
  	$return['width'] = 310;
  	$return['height'] = round(310 * $ratio);
  	$return['src'] = $src;
  	return $return;
  }
  
  
  function format($content) {
  	preg_match_all('/<img[^>]*>/i',$content,$images);
  	$scanned = array();
  	foreach($images[0] as $image) {
  		if(!in_array(md5($image),$scanned)) {
	  		$scanned[] = md5($image);
	  		//preg_match('/(src="[^"]+")|(width="[0-9]{1,4}")|(height="[0-9]{1,4})"/i',$image,$attr);
	  		$img = array();
	  		preg_match_all('/(src|width|height)=("[^"]*")/i',$image, $attr);
	  		foreach($attr[0] as $tmp) {
	  			if(preg_match('/src/i',$tmp)) {
	  				$img['src'] = str_replace(array('src=','"'),'',$tmp);
	  				if(substr($img['src'],0,4)!='http') {
	  					$nimg['src'] = BASE_URL.'/'.$img['src'];
	  				} else {
	  					$nimg['src'] = $img['src'];
	  				}
	  			} elseif(preg_match('/width/i',$tmp)) {
	  				$img['width'] = preg_replace('/[^0-9]/','',$tmp);
	  			} elseif(preg_match('/height/i',$tmp)) {
	  				$img['height'] = preg_replace('/[^0-9]/','',$tmp);
	  			}
	  		}
	 
	  		if(isset($img['width']) && isset($img['height']) && isset($img['src'])) {
	  			if($img['width'] > 310) {
	  				$ratio = $img['height']/$img['width'];
					$nimage = $this->resize($nimg['src'],$ratio);
					$nimage = str_replace(array($img['width'],$img['height'],$img['src']),array($nimage['width'],$nimage['height'],$nimage['src']),$image);
					$content = str_replace($image,$nimage,$content);
	  			}
	  		} elseif(isset($img['src'])) {
	  			list($width,$height) = @getimagesize($nimg['src']);
	  			if($img['width'] > 310) {
	  				$ratio = $height/$width;
					$nimage = $this->resize($nimg['src'],$ratio);
					$nimage = str_replace(array($img['width'],$img['height'],$img['src']),array($nimage['width'],$nimage['height'],$nimage['src']),$image);
	  				$content = str_replace($image,$nimage,$content);
	  			}
	  		}
	  		
  			if($nimg['src'] != $img['src']) {
	  			$nimage = str_replace($img['src'],$nimg['src'],$image);
	  			$content = str_replace($image,$nimage,$content);
	  		}
  		}
  	}
  	
  	// OBJECTS & EMBED
  	preg_match_all('/<(object|embed)[^>]*>/i',$content,$objects);
  	foreach($objects[0] as $object) {
  		if(!in_array(md5($object),$scanned)) {
	  		$scanned[] = md5($object);
	  		//preg_match('/(src="[^"]+")|(width="[0-9]{1,4}")|(height="[0-9]{1,4})"/i',$image,$attr);
	  		$obj = array();
	  		preg_match_all('/(width|height)=("[^"]*")/i',$object, $attr);
	  		foreach($attr[0] as $tmp) {
	  			if(preg_match('/width/i',$tmp)) {
	  				$obj['width'] = preg_replace('/[^0-9]/','',$tmp);
	  			} elseif(preg_match('/height/i',$tmp)) {
	  				$obj['height'] = preg_replace('/[^0-9]/','',$tmp);
	  			}
	  		}
	 
	  		if(isset($obj['width']) && isset($obj['height'])) {
	  			if($obj['width'] > 310) {
	  				$ratio = $obj['height']/$obj['width'];
					$nobject = $this->resize('',$ratio);
					$nobject = str_replace(array($obj['width'],$obj['height']),array($nobject['width'],$nobject['height']),$object);
	  				$content = str_replace($object,$nobject,$content);
	  			}
	  		}
  		}
  	}
  	
  	// REWRITE LINKS
    preg_match_all('/<a[^>]*>/i',$content,$links);
  	foreach($links[0] as $link) {
  		if(!in_array(md5($link),$scanned)) {
	  		$scanned[] = md5($link);

	  		preg_match_all('/href="([^"]*)"/i',$link, $attr);
	 		if(isset($attr[1][0])) {
	 			$nlink = str_replace($attr[1][0],SUB_PAGES.'?url='.urlencode((preg_match('/^(\/|http:\/\/)|(https:\/\/)|(mailto:)|(ftp:\/\/)/i',$attr[1][0])?'':$this->folder).$attr[1][0]),$link);
	 			$content = str_replace($link,$nlink,$content);
	 		}
  		}
  	}
  
  	
  	return $content;
  	
  }
}


// NON - APC SETUP
//$dom->manual_startup('http://www.carm.org');
//echo $dom->innerHTML('content');

// MUCH NICER, APC SETUP
//echo $doc->fetch('http://www.mikestowe.com','content'); ?>
