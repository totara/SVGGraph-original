<?php

require_once(__DIR__ . '/../SVGGraph.php');

$settings = array(
  'back_colour' => '#fff',  'stroke_colour' => '#000',
  'back_stroke_width' => 0, 'back_stroke_colour' => '#eee',
  'axis_colour' => '#333',  'axis_overlap' => 2,
  'axis_font' => 'Georgia', 'axis_font_size' => 10,
  'grid_colour' => '#ddd',  'label_colour' => '#000',
  'pad_right' => 20,        'pad_left' => 20,
  'link_base' => '/',       'link_target' => '_top',
  'minimum_grid_spacing' => 20,

  'crosshairs' => true,

  'preserve_aspect_ratio' => 'xMidYMid meet',
  'auto_fit' => true,
);

$settings['legend_entries'] = array('First series', 'Second series', 'Third series');

$values = array(
  array('Dough' => 30, 'Ray' => 50, 'Me' => 40, 'So' => 25, 'Far' => 45, 'Lard' => 35),
  array('Dough' => 20, 'Ray' => 30, 'Me' => 20, 'So' => 15, 'Far' => 25, 'Lard' => 35, 'Tea' => 45),
  array('Dough' => 11, 'Ray' => 11, 'Me' => 20, 'So' => 7, 'Far' => 40, 'Lard' => 22, 'Tea' => 1),
);

// Colors are copied from D3 that used http://colorbrewer2.org by Cynthia Brewer, Mark Harrower and The Pennsylvania State University
$colours = array(
  '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', '#9467bd',
  '#8c564b', '#e377c2', '#7f7f7f', '#bcbd22', '#17becf');

$links = array('Dough' => 'jpegsaver.php', 'Ray' => 'crcdropper.php',
               'Me' => 'svggraph.php');

if (!empty($_GET['object'])) {
  $graph = new SVGGraph(1000, 300, $settings);
  $graph->colours = $colours;
  $graph->Values($values);
  $graph->Links($links);
  $graph->Render('GroupedBarGraph');
  die;
}

/*

Some more reading:
 * http://thatemil.com/blog/2014/04/06/intrinsic-sizing-of-svg-in-responsive-web-design/
 * http://alistapart.com/article/creating-intrinsic-ratios-for-video
 * http://stackoverflow.com/questions/25566109/chrome-bug-in-svg-getscreenctm

*/

?>
<!DOCTYPE html>
<html  dir="ltr" lang="en" xml:lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <style>
    .parentwithheight {
      height: 100px;
    }
    .parentwithwidth {
      width: 500px;
    }
    @media screen and (-ms-high-contrast: active), (-ms-high-contrast: none) {
      /* IE10+ specific styles go here */
      .wrapperdiv {
        position: relative;
        padding-bottom: 30%;  /* This is the ration of original svg size 1000x300 */
        height: 0;
      }
      .wrapperdiv svg,
      .wrapperdiv object {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
      }
    }
  </style>
  <!--[if IE]>
  <style>
    /* IE < 10 styles */
    .wrapperdiv {
      position: relative;
      padding-bottom: 30%;  /* This is the ration of original svg size 1000x300 */
      height: 0;
    }
    .wrapperdiv svg,
    .wrapperdiv object {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
    }
  </style>
  <![endif]-->
</head>
<body>
<h1>Scaling sample</h1>
<h2>Scaled to parent with height 100</h2>
<h3>Inline SVG</h3>
<div class="parentwithheight">
  <?php
  $graph = new SVGGraph(1000, 300, $settings);
  $graph->colours = $colours;
  $graph->Values($values);
  $graph->Links($links);
  echo $graph->Fetch('GroupedBarGraph', false, false);
  ?>
</div>
<h3>SVG embedded as object</h3>
<div class="parentwithheight">
  <object type="image/svg+xml" data="scaling.php?object=1" width="100%" height="100%">svg object</object>
</div>

<h2>Scaled to parent with width 500</h2>
<h3>Inline SVG</h3>
<div class="parentwithwidth">
  <?php
  $graph = new SVGGraph(1000, 300, $settings);
  $graph->colours = $colours;
  $graph->Values($values);
  $graph->Links($links);
  echo $graph->Fetch('GroupedBarGraph', false, false);
  ?>
</div>
<h3>SVG embedded as object</h3>
<div class="parentwithwidth">
  <object type="image/svg+xml" data="scaling.php?object=1" width="100%" height="100%">svg object</object>
</div>

<h2>Scaled to page width</h2>
<h3>Inline SVG</h3>
<div class="wrapperdiv">
  <?php
  $graph = new SVGGraph(1000, 300, $settings);
  $graph->colours = $colours;
  $graph->Values($values);
  $graph->Links($links);
  echo $graph->Fetch('GroupedBarGraph', false, false);
  ?>
</div>
<h3>SVG embedded as object</h3>
<div class="wrapperdiv">
  <object type="image/svg+xml" data="scaling.php?object=1" width="100%" height="100%">svg object</object>
</div>
<h2>Pie Graph</h2>
  <div class="wrapperdiv">
    <?php
    $settings = array();
    $settings['label_fade_in_speed'] = 30;
    $settings['label_fade_out_speed'] = 15;
    $settings['preserve_aspect_ratio'] = 'xMidYMid meet';
    $settings['auto_fit_parent'] = true;
    $settings['units_before_tooltip'] = '&#xa3;';

    $graph = new SVGGraph(1000, 300, $settings);
    $graph->colours = $colours;
    $graph->Values($values[0]);
    echo $graph->Fetch('PieGraph', false, false);
    ?>
  </div>
</body>
</html>
