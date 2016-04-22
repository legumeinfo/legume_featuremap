<?php
// CUSTOMIZATIONS:
//  - list only linkage groups
//  - no pager
//  - assumes linkage groups, so gets all featurepos records for 
//      map_feature_id=linkage group, then calculates start/stop positions
//  - add CMap link to linkage group table

$featuremap = $variables['node']->featuremap;
//echo "initial featuremap:<pre>";var_dump($featuremap);echo "</pre>";


// HACK: Why is featurepos sometimes attached to the initial $featuremap object? 
//       For now, just check and unset:
if (isset($featuremap->featurepos)) {
  unset($featuremap->featurepos);
};


$feature_positions = array();

// expand the featuremap object to include the records from the featurepos table
// specify the number of features to show by default and the unique pager ID
// CUSTOMIZATION: will list all linkage groups on one page
$num_results_per_page = 10000; //25; 
$featurepos_pager_id = 0;

// get the features aligned on this map
$options = array(  
  'return_array' => 1,
  'order_by' => array('map_feature_id' => 'ASC'),
// CUSTOMIZATION: will list all linkage groups on one page
//  'pager' => array(
//    'limit' => $num_results_per_page, 
//    'element' => $featurepos_pager_id
//  ),
  'include_fk' => array(
    'map_feature_id' => array(
      'type_id' => 1,
      'organism_id' => 1,
    ),
    'feature_id' => array(
      'type_id' => 1,
    ),
    'featuremap_id' => array(
       'unittype_id' => 1,
    ),
  ),

// CUSTOMIZATION: limit features loaded to linkage_group features
  'filter' => array(
    'feature_id' => array(
      'type_id' => array(
        'cv_id' => array(
          'name' => 'sequence',
        ),
        'name' => 'linkage_group',
      ),
    ),
  ),
);

$featuremap = chado_expand_var($featuremap, 'table', 'featurepos', $options);
//echo "featuremap:<pre>";var_dump($featuremap);echo "</pre>";
$feature_positions = $featuremap->featurepos;
//echo "feature positions:<pre>";var_dump($feature_positions);echo "</pre>";


// CUSTOMIZATION: limit features loaded to linkage_group features and don't 
//                use pager.
// get the total number of linkage groups
//$total_features = chado_pager_get_count($featurepos_pager_id);
$total_features = count($feature_positions);

// CUSTOMIZATION: because assuming linkage groups and each will have two map 
//                positions, half the record count to get the # of linkage groups
$lg_count = $total_features/2;

// CUSTOMIZATION: limit features loaded to linkage_group features
if (count($feature_positions) > 0) { ?>
  <div class="tripal_featuremap-data-block-desc tripal-data-block-desc">
    This map contains <?php print number_format($lg_count) ?> linkage groups:
  </div> 

<?php 
// CUSTOMIZATION: only linkage groups listed, include CMap links.
  // the $headers array is an array of fields to use as the colum headers.
  // additional documentation can be found here
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
//  $headers = array('Landmark', 'Type', 'Organism', 'Feature Name', 'Type', 'Position');
  $headers = array('Feature Name', 'link out', 'Start', 'End');
  
  // the $rows array contains an array of rows where each row is an array
  // of values for each column of the table in that row.  Additional documentation
  // can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  $rows = array();
  
// HACK: since assuming feature is a linkage group and attaching 
//       featureposprop records isn't working, create an an to hold 
//       all information for a linkage group.
  $lgs = array();
  
  foreach ($feature_positions as $position) {
    $map_feature = $position->map_feature_id;
    $feature     = $position->feature_id;  
    $organism    = $map_feature->organism_id; 

    // check if there are any values in the featureposprop table for the start and stop
    $mappos = $position->mappos;
    $options = array(
      'return_array' => 1,
      'include_fk' => array(
        'type_id' => 1,            
      ),
    );
//NOTE: effort to expand featureposprop records is (sometimes?) failing:
    $position = chado_expand_var($position, 'table', 'featureposprop', $options);
    $featureposprop = $position->featureposprop;    
    $start = 0;
    $stop = 0;
    if (is_array($featureposprop)) {
      foreach ($featureposprop as $index => $property) {
         if ($property->type_id->name == 'start') {
           $start = $property->value;
         }
         if ($property->type_id->name == 'stop') {
           $stop = $property->value;
         }
      }      
    }  
    if ($start and $stop and $start != $stop) {
      $mappos = "$start-$stop";
    }
    if ($start and !$stop) {
      $mappos = $start;
    } 
    if ($start and $stop and $start == $stop) {
      $mappos = $start;
    }
    
    $mfname = $map_feature->name;
    if (property_exists($map_feature, 'nid')) {
      $mfname =  l($mfname, 'node/' . $map_feature->nid, array('attributes' => array('target' => '_blank')));
    }
    $orgname = $organism->genus ." " . $organism->species ." (" . $organism->common_name .")";
    if (property_exists($organism, 'nid')) {
      $orgname = l(
        "<i>" . $organism->genus . " " . $organism->species . "</i> (" . $organism->common_name .")", 
        "node/". $organism->nid, 
        array('html' => TRUE, 'attributes' => array('target' => '_blank'))
      );
    }
    $organism =  $organism->genus . ' ' . $organism->species;
    
    $fname = $feature->name;
    if (property_exists($feature, 'nid')) {
      $fname = l($fname, 'node/' . $feature->nid, array('attributes' => array('target' => '_blank')));
    }

// HACK: since assuming feature is a linkage group and attaching 
//       featureposprop records isn't working, create an an to hold 
//       all information for a linkage group.
//    $rows[] = array(
//      $mfname,
//      $map_feature->type_id->name,
//      $orgname,
//      $fname,
//      $feature->type_id->name,
//      $mappos . ' ' . $position->featuremap_id->unittype_id->name
//    );

    if (!isset($lgs[$position->map_feature_id->feature_id])) {
      $lg_feature = $position->map_feature_id;
      
// CUSTOMIZATION: Get CMap linkout, if any
      $cmap_link = 'none';
      $lg_feature = chado_expand_var($lg_feature, 'table', 'feature_dbxref', 
                                 array('return_array' => 1)
      );

      // Not a good solution: assumes there will be at most one feature_dbxref
      //    and that it will be a CMap linkout.
      if (count($lg_feature->feature_dbxref) > 0) {
        $dbxref = $lg_feature->feature_dbxref[0]->dbxref_id;
        if ($dbxref->accession != '') {
          $url = $dbxref->db_id->urlprefix . $dbxref->accession;
          $cmap_link = "<a href=\"$url\">CMap</a>";
        }
      }
      
      $lgs[$position->map_feature_id->feature_id] = array(
        'name' => $fname,
        'type' => $feature->type_id->name,
        'cmap' => $cmap_link,
        'mappos1' => $mappos,
        'unit' => $position->featuremap_id->unittype_id->name
      );
    }
    else {
      $lgs[$position->map_feature_id->feature_id]['mappos2'] = $mappos;
    }
  }

// HACK: since assuming feature is a linkage group and attaching 
//       featureposprop records isn't working, create an an to hold 
//       all information for a linkage group.
  asort($lgs);  // sort into alphabetical order (yes, this will jumble some numbers)
  foreach ($lgs as $lg) {
    if ($lg['mappos1'] < $lg['mappos2']) {
      $start = $lg['mappos1'];
      $end = $lg['mappos2'];
    }
    else {
      $start = $lg['mappos2'];
      $end = $lg['mappos1'];
    }
    $rows[] = array(
      $lg['name'],
      $lg['cmap'],
      $start . ' ' . $lg['unit'],
      $end . ' ' . $lg['unit'],
    );
  }
  
    
  // the $table array contains the headers and rows array as well as other
  // options for controlling the display of the table.  Additional
  // documentation can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  $table = array(
    'header' => $headers,
    'rows' => $rows,
    'attributes' => array(
      'id' => 'tripal_featuremap-table-featurepos',
      'class' => 'tripal-data-table'
    ),
    'sticky' => FALSE,
    'caption' => '',
    'colgroups' => array(),
    'empty' => '',
  );
  
  // once we have our table array structure defined, we call Drupal's theme_table()
  // function to generate the table.
  print theme_table($table);

  // the $pager array values that control the behavior of the pager.  For
  // documentation on the values allows in this array see:
  // https://api.drupal.org/api/drupal/includes!pager.inc/function/theme_pager/7
  // here we add the paramter 'block' => 'features'. This is because
  // the pager is not on the default block that appears. When the user clicks a
  // page number we want the browser to re-appear with the page is loaded.
  $pager = array(
    'tags' => array(),
    'element' => $featurepos_pager_id,
    'parameters' => array(
      'block' => 'featurepos'
    ),
    'quantity' => $num_results_per_page,
  );
  print theme_pager($pager); 
}

