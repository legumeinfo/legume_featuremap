<?php

$featuremap  = $variables['node']->featuremap;

// expand the description field
$featuremap = chado_expand_var($featuremap, 'field', 'featuremap.description');

// CUSTOMIZE: add properties to this page

// So that chado variables are returned in an array
$options = array('return_array' => 1);

$featuremap = chado_expand_var($featuremap, 'table', 'featuremapprop', $options);
$properties = $featuremap->featuremapprop;
$more_rows = array();
foreach ($properties as $property) {
  $property = chado_expand_var($property,'field','featuremapprop.value');
  $prop_type = $property->type_id->name;
  $more_rows[$prop_type] = $value = urldecode($property->value);
}
?>

<div class="tripal_featuremap-data-block-desc tripal-data-block-desc"></div> <?php 

// the $headers array is an array of fields to use as the colum headers. 
// additional documentation can be found here 
// https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
// This table for the analysis has a vertical header (down the first column)
// so we do not provide headers here, but specify them in the $rows array below.
$headers = array();

// the $rows array contains an array of rows where each row is an array
// of values for each column of the table in that row.  Additional documentation
// can be found here:
// https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7 
$rows = array();

// Map Name row
$rows[] = array(
  array(
    'data' => 'Map Name',
    'header' => TRUE,
    'width' => '20%',
  ),
  $featuremap->name
);

// CUSTOMIZATION: add publication map name here
$rows[] = array(
  array(
    'data' => 'Publication Map Name',
    'header' => TRUE
  ), 
  $more_rows['Publication Map Name']
);

// Map Units
$rows[] = array(
  array(
    'data' => 'Map Units',
    'header' => TRUE
  ),
  $featuremap->unittype_id->name
);


// CUSTOMIZATION: CMap link
$cmap_link = 'none';
$featuremap = chado_expand_var($featuremap, 'table', 'featuremap_dbxref', $options);
$dbxrefs = $featuremap->featuremap_dbxref;
foreach ($dbxrefs as $dbxref) {
  if ($dbxref->dbxref_id->db_id->name == 'LIS:cmap') {
    $urlprefix = $dbxref->dbxref_id->db_id->urlprefix;
    $cmap_link = "<a href=\"$urlprefix" . $dbxref->dbxref_id->accession . '">';
    $cmap_link .= 'CMap</a>';
    break;
  }
}
$rows[] = array(
  array(
    'data' => 'Map view',
    'header' => TRUE
  ),
  $cmap_link
);


// CUSTOMIZATION: get species from mapping population
$species_array = array();
$args = array('featuremap_id' => $featuremap->featuremap_id);
$featuremap_stock = chado_generate_var('featuremap_stock', $args);
if ($stock = $featuremap_stock->stock_id) {
  $args = array('stock_id' => $stock->stock_id);
  // create a new stock_organism chado variable
  $stock_organisms = chado_generate_var('stock_organism', $args);
  if ($stock_organisms && count($stock_organisms) > 0) {
    foreach ($stock_organisms as $stock_organism) {
      $organism = $stock_organism->organism_id;
//echo "organism: <pre>";var_dump($organism);echo "</pre>";
      $species_array[] = $organism->genus . ' ' . $organism->species; 
    }
  }
  else {
    $species_array[] = $stock->organism_id->genus . ' ' . $stock->organism_id->species;
  }
}
else {
  $featuremap = chado_expand_var($featuremap, 'table', 'featurepos', 
                                 array('limit' => 1));
  $featurepos = $featuremap->featurepos;
  $organism = $featurepos->map_feature_id->organism_id;
//echo "organism: <pre>";var_dump($organism);echo "</pre>";
  $species_array[] = $organism->genus . ' ' . $organism->species;
}

$rows[] = array(
  array(
    'data' => 'Species',
    'header' => TRUE
  ),
  '<i>' . implode($species_array, ', ') . '</i>'
);

// CUSTOM: add pre-defined property fields here
$rows[] = array(
  array(
    'data' => 'Population Size',
    'header' => TRUE
  ), 
  $more_rows['Population Size']
);
$rows[] = array(
  array(
    'data' => 'Population Type',
    'header' => TRUE
  ), 
  $more_rows['Population Type']
);
$rows[] = array(
  array(
    'data' => 'Methods',
    'header' => TRUE
  ), 
  $more_rows['Methods']
);
$rows[] = array(
  array(
    'data' => 'Description',
    'header' => TRUE
  ),
  $featuremap->description
);
$rows[] = array(
  array(
    'data' => 'Comment',
    'header' => TRUE
  ), 
  $more_rows['Featuremap Comment']
);

// allow site admins to see the feature ID
if (user_access('view ids')) {
  // Feature Map ID
  $rows[] = array(
    array(
      'data' => 'Feature Map ID',
      'header' => TRUE,
      'class' => 'tripal-site-admin-only-table-row',
    ),
    array(
      'data' => $featuremap->featuremap_id,
      'class' => 'tripal-site-admin-only-table-row',
    ),
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
    'id' => 'tripal_featuremap-table-base',
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

// CUSTOMIZATION: show description in table above
/*
if (property_exists($featuremap, 'description')) { ?>
  <div style="text-align: justify"><?php print $featuremap->description; ?></div> <?php  
} 
*/