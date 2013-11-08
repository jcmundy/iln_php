<?php

// pass article id as argument, for example:
// browse.php?id=iln38.1068.002
// optionally, pass search terms for highlighting; for example;
// browse.php?id=iln38.1068.002&term=lincoln  

include("config.php");
include("common_functions.php");
include_once("lib/xmlDbConnection.class.php");

$id = $_GET["id"];
$kw = $_GET["keyword"];

$exist_args{"debug"} = false;
$xmldb = new xmlDbConnection($exist_args);
//$xql = "TEI.2//div1/div2[@id='" . $id . "']"; 

$query = "declare option exist:serialize 'highlight-matches=all';"; 
$query .= 'for $art in /TEI.2//div1/div2[@id = "' . "$id" . '"]';
if ($kw != '') {$query .= "[. |= \"$kw\"]";}
$query .= 'let $hdr := root($art)/TEI.2/teiHeader
let $previd := $art/preceding-sibling::div2[1]
let $nextid := $art/following-sibling::div2[1]
let $issue := $art/..
return <TEI>
{$hdr}
{$art}
<issueid>
{$issue/@id}
{$issue/head}
</issueid>
<siblings>
    <prev>
    {$previd/@id}
    {$previd/@type}
    {$previd/@n}
    {$previd/bibl}
</prev>
<next>
 {$nextid/@id}
 {$nextid/@type}
 {$nextid/@n}
 {$nextid/bibl}
</next>
</siblings>
</TEI>
';

// addition to the query for next/previous links (only in contents/browse mode, not searches) 

//use @n in nextid-previd because head is in figure element for Illustrations.

$xsl_file = "xslt/article.xsl"; 

if ($id) {
  // run the query 
  $xmldb->xquery($query);


$header_xsl1 = "xslt/teiheader-dc.xsl";
$header_xsl2 = "xslt/dc-htmldc.xsl";
$xmldb->xslTransform($header_xsl1);
$xmldb->xslTransformResult($header_xsl2);

/*$xmldb->xslBind($header_xsl1);
$xmldb->xslBind($header_xsl2);
$xmldb->transform();*/

html_head("Browse - Article", true);
  $xmldb->printResult();
print '</head>';
include("web/xml/head.xml");
include("web/xml/sidebar.xml");
print '<div class="content">';




  // transform xml with xslt
  $xmldb->xslTransform($xsl_file);
  $xmldb->printResult();
} else {
  print "<p class='error'>Error: No article specified!</p>";
}

include("searchformart.php");

print "</div>"; 
include("web/xml/foot.xml"); 
?>

</body>
</html>