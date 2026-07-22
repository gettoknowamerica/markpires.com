<?php
if(!function_exists('goliath_fix_first_letter')){
function goliath_fix_first_letter($value,$type='text'){
 $v=(string)$value; if($v==='')return $v;
 $exec=['oliath'=>'goliath','essica'=>'jessica','cout'=>'scout','corsese'=>'scorsese','ozart'=>'mozart','hakespeare'=>'shakespeare','instein'=>'einstein','olumbo'=>'columbo','rospector'=>'prospector','ockefeller'=>'rockefeller','andora'=>'pandora','olmes'=>'holmes'];
 $town=['tamford'=>'Stamford','tanford'=>'Stamford','reenwich'=>'Greenwich','estport'=>'Westport','airfield'=>'Fairfield','orwalk'=>'Norwalk','ew Canaan'=>'New Canaan','ewcanaan'=>'New Canaan','idgefield'=>'Ridgefield','ilton'=>'Wilton','eston'=>'Weston','arien'=>'Darien','onroe'=>'Monroe','rumbull'=>'Trumbull','helton'=>'Shelton','tratford'=>'Stratford','ridgeport'=>'Bridgeport'];
 $slug=['/blog/tamford'=>'/blog/stamford','blog/tamford'=>'blog/stamford','tamford-home-selling-guide'=>'stamford-home-selling-guide'];
 if($type==='executive'){$k=strtolower(trim($v));$k=preg_replace('/[^a-z]/','',$k);return $exec[$k]??$k;}
 foreach($slug as $b=>$g)$v=str_replace($b,$g,$v);
 foreach($town as $b=>$g)$v=preg_replace('/\b'.preg_quote($b,'/').'\b/i',$g,$v);
 foreach($exec as $b=>$g)$v=preg_replace('/\b'.preg_quote($b,'/').'\b/i',$g,$v);
 return $v;
}}
if(!function_exists('goliath_town_slug')){function goliath_town_slug($town){$town=goliath_fix_first_letter($town,'town');$slug=strtolower(trim($town));$slug=preg_replace('/[^a-z0-9]+/','-',$slug);return trim($slug,'-');}}
if(!function_exists('goliath_recommended_blog_for_town')){function goliath_recommended_blog_for_town($town,$topic='home-selling-guide'){$slug=goliath_town_slug($town);return '/blog/'.($slug?:'fairfield-county').'-'.$topic.'.html';}}
?>