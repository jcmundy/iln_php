<?php 

class taminoConnection {

  // connection parameters
  var $host;
  var $db;
  var $coll;
  // whether or not to display debugging information
  var $debug;
  
  // these variables used internally
  var $base_url;
  var $xmlContent;
  var $xml;
  var $xpath;
  var $xsl_result;
  var $xq_rval;
  var $xq_code;
  var $xq_msg;

  // cursor variables
  var $cursor;
  var $count;
  var $position;

  // variables for highlighting search terms
  var $begin_hi;
  var $end_hi;


  

  function taminoConnection($argArray) {
    $this->host = $argArray['host'];
    $this->db = $argArray['db'];
    $this->coll = $argArray['coll'];
    $this->debug = $argArray['debug'];

    $this->base_url = "http://$this->host/tamino/$this->db/$this->coll?";

    // variables for highlighting search terms
    $this->begin_hi[0]  = "<span class='term1'><b>";
    $this->begin_hi[1] = "<span class='term2'><b>";
    $this->begin_hi[2] = "<span class='term3'><b>";
    $this->end_hi = "</b></span>";
  }

  // send an xquery to tamino & get xml result
  // returns  tamino error code (0 for success, non-zero for failure)
  function xquery ($query) {
    $myurl = $this->base_url . "_xquery=" . $this->encode_xquery($query);
    if ($this->debug) {
      print "DEBUG: In function taminoConnection::xquery, url is $myurl.<p>";
    }

    $this->xmlContent = file_get_contents($myurl);
    if ($this->xmlContent) {
      $this->initializeXML();
      if ($this->debug) {
        $this->displayXML();
      }
      
       if (!($this->xq_rval)) {    // tamino Error code (0 = success)
         $this->getXQueryCursor();
       } else if ($this->xq_rval == "8306") {
       // invalid cursor position (also returned when there are no matches)
         $this->count = $this->position = $this->quantity = 0;
         if ($debug) {
  	   print "DEBUG: Tamino error 8306 = invalid cursor position<br>\n";
         }
        } else if ($this->xq_rval) {
           $this->count = $this->position = $this->quantity = 0;
           print "<p>Error: failed to retrieve contents.<br>";
           print "(Tamino error code $error)</p>";
        }

    } else {
      print "<p><b>Error:</b> unable to access database.</p>";
      $this->xq_rval = -1;
    }

   return $this->xq_rval;
  }


  // send an x-query (xql) to tamino & get xml result
  // returns  tamino error code (0 for success, non-zero for failure)
  // optionally allows for use of xql-style cursor
  function xql ($query, $position = NULL, $maxdisplay = NULL) {
    if ($this->debug) {
      print "DEBUG: In function taminoConnection::xql, query is $query.<p>";
    }
    if (isset($position) && isset($maxdisplay)) {
      $xql = "_xql($position,$maxdisplay)=";
    } else {
      $xql = "_xql=";
    }
    $myurl = $this->base_url . $xql . $this->encode_xquery($query);
    if ($this->debug) {
      print "DEBUG: In function taminoConnection::xql, url is $myurl.<p>";
    }
    $this->xmlContent = file_get_contents($myurl);

    if ($this->xmlContent) {
      $this->initializeXML();
      if ($this->debug) {
        $this->displayXML();
      }
    } else {
      print "<p><b>Error:</b> unable to access database.</p>";
      $this->xq_rval = -1;
    }
    
    return $this->xq_rval;
  }

   // convert a readable xquery into a clean url for tamino
   function encode_xquery ($string) {
     // get rid of multiple white spaces
     $string = preg_replace("/\s+/", " ", $string);
     // convert spaces to their hex equivalent
     $string = str_replace(" ", "%20", $string);
     return $string;
   }

   // retrieve the cursor & get the total count
   function getCursor () {
     // NOTE: this is an xql style cursor, not xquery
     if ($this->xml) {
       $nl = $this->xpath->query("/ino:response/ino:cursor/@ino:count");
       if ($nl) { $this->count = $nl->item(0)->textContent; }
     } else {
       print "Error! taminoConnection xml variable uninitialized.<br>";
     }
   }


      // retrieve the XQuery style cursor & get the total count
   function getXQueryCursor () {
     if ($this->xml) {
       $nl = $this->xpath->query("/ino:response/ino:cursor/ino:current/@ino:position");
       if ($nl) {  $this->position = $nl->item(0)->textContent; }
       $nl = $this->xpath->query("/ino:response/ino:cursor/ino:current/@ino:quantity");
       if ($nl) { $this->quantity = $nl->item(0)->textContent; }

       $total = $this->xml->getElementsByTagName("total");
       if ($total) { $this->count = $total->item(0)->textContent; }
     } else {
       print "Error! taminoConnection xml variable uninitialized.<br>";
     }
   }


   // transform the tamino XML with a specified stylesheet
   function xslTransform ($xsl_file, $xsl_params = NULL) {
     /* load xsl & xml as DOM documents */
     $xsl = new DomDocument();
     $xsl->load("xsl/$xsl_file");

     /* create processor & import stylesheet */
     $proc = new XsltProcessor();
     $xsl = $proc->importStylesheet($xsl);
     if ($xsl_params) {
       foreach ($xsl_params as $name => $val) {
         $proc->setParameter(null, $name, $val);
       }
     }
     /* transform the xml document and store the result */
     $this->xsl_result = $proc->transformToDoc($this->xml);
   }

   function printResult ($term = NULL) {
     if (isset($term[0])) {
       $this->highlight($term);
     }
     print $this->xsl_result->saveXML();

   }

   // Highlight the search strings within the xsl transformed result.
   // Takes an array of terms to highlight.
   function highlight ($term) {
     // note: need to fix regexps: * -> \w* (any word character)
      // FIXME: how best to deal with wild cards?

     // only do highlighting if the term is defined
     for ($i = 0; (isset($term[$i]) && ($term[$i] != '')); $i++) {
       // replace tamino wildcard (*) with regexp -- 1 or more word characters 
       $_term = str_replace("*", "\w+", $term[$i]);
     // Note: regexp is constructed to avoid matching/highlighting the terms in a url 
       $this->xsl_result = preg_replace("/([^=|']\b)($_term)(\b)/i",
	      "$1" . $this->begin_hi[$i] . "$2$this->end_hi$3", $this->xsl_result);
     }
   }

   // print out search terms, with highlighting matching that in the text
   function highlightInfo ($term) {
     if (isset($term[0])) {
       print "<p align='center'>The following search terms have been highlighted: ";
       for ($i = 0; isset($term[$i]); $i++) {
	 print "&nbsp; " . $this->begin_hi[$i] . "$term[$i]$this->end_hi &nbsp;";
       }
       print "</p>";
     }
   }


   // create a new domDocument with the raw xmlContent, retrieve tamino messages
   function initializeXML () {
    $this->xml = new domDocument();
    $this->xml->loadXML($this->xmlContent);
    if (!$this->xml) {
      print "TaminoConnection::xquery error: unable to parse xml content.<br>";
      $this->xq_rval = 0;	// not a tamino error but a dom error
    } else {
     $this->xpath = new domxpath($this->xml);
     // note: query returns a dome node list object
     $nl = $this->xpath->query("/ino:response/ino:message/@ino:returnvalue");
     if ($nl) { $this->xq_rval = $nl->item(0)->textContent; }
     $nl = $this->xpath->query("/ino:response/ino:message/ino:messagetext/@ino:code");
     if ($nl) { $this->xq_code = $nl->item(0)->textContent; }
     $nl =  $this->xpath->query("/ino:response/ino:message/ino:messagetext");
     if ($nl) { $this->xq_msg = $nl->item(0)->textContent; }
     if ($this->debug) {
       print "Tamino return value : $this->xq_rval<br>\n";
       if ($this->xq_code) {
         print "Tamino code : $this->xq_code<br>\n";
       }
       if ($this->xq_msg) {
	 print "Tamino message : $this->xq_msg<br>\n";
       }
     }
    }
   }

   // print out xml (for debugging purposes)
   function displayXML () {
     if ($this->xml) {
       $this->xml->formatOutput = true;
       print "<pre>";
       print htmlentities($this->xml->saveXML());
       print "</pre>";
     }
   }
   
}