<?php
/**
 * Copyright (C) 2009-2012 Graham Breach
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 * 
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
/**
 * For more information, please contact <graham@goat1000.com>
 */

require_once 'SVGGraphAxis.php';
require_once 'SVGGraphAxisFixed.php';

define("SVGG_GUIDELINE_ABOVE", 1);
define("SVGG_GUIDELINE_BELOW", 0);

abstract class GridGraph extends Graph {

  protected $bar_unit_width = 0;
  protected $x0;
  protected $y0;
  protected $y_points;
  protected $x_points;

  // set to true for horizontal graphs
  protected $flip_axes = false;

  // set to true for block-based labelling
  protected $label_centre = false;

  protected $g_width = null;
  protected $g_height = null;
  protected $uneven_x = false;
  protected $uneven_y = false;
  protected $label_adjust_done = false;
  protected $axes_calc_done = false;
  protected $sub_x;
  protected $sub_y;
  protected $guidelines = array();
  protected $min_guide = array('x' => null, 'y' => null);
  protected $max_guide = array('x' => null, 'y' => null);

  private $label_left_offset;
  private $label_bottom_offset;

  /**
   * Modifies the graph padding to allow room for labels
   */
  protected function LabelAdjustment($longest_v = 1000, $longest_h = 100)
  {
    // deprecated options need converting
    // NOTE: this works because graph settings become properties, whereas
    // defaults only exist in the $this->settings array
    if(isset($this->show_label_h) && !isset($this->show_axis_text_h))
      $this->show_axis_text_h = $this->show_label_h;
    if(isset($this->show_label_v) && !isset($this->show_axis_text_v))
      $this->show_axis_text_v = $this->show_label_v;

    // use length of $longest, or make a guess at length of numbers
    $len_v = strlen(is_string($longest_v) ? $longest_v :
      $this->NumString($longest_v)) + 1;
    $len_h = strlen(is_string($longest_h) ? $longest_h :
      $this->NumString($longest_h)) + 1;

    // if the label_x or label_y are set but not _h and _v, assign them
    $lh = $this->flip_axes ? $this->label_y : $this->label_x;
    $lv = $this->flip_axes ? $this->label_x : $this->label_y;
    if(empty($this->label_h) && !empty($lh))
      $this->label_h = $lh;
    if(empty($this->label_v) && !empty($lv))
      $this->label_v = $lv;

    if(!empty($this->label_v)) {
      // increase padding
      $lines = $this->CountLines($this->label_v);
      $this->label_left_offset = $this->pad_left + $this->label_space +
        $this->label_font_size;
      $this->pad_left += $lines * $this->label_font_size +
        2 * $this->label_space;
    }
    if(!empty($this->label_h)) {
      $lines = $this->CountLines($this->label_h);
      $this->label_bottom_offset = $this->pad_bottom + $this->label_space +
        $this->label_font_size * ($lines - 1);
      $this->pad_bottom += $lines * $this->label_font_size +
        2 * $this->label_space;
    }
    if($this->show_axes) {
      if($this->show_axis_text_v) {
        // modify padding for axis markings - this is the font height,
        // plus the cosine of the text angle x size x length-1 x adjustment
        $this->pad_left += ($this->axis_font_size * ($len_v - 1) *
          $this->axis_font_adjust *
          cos(deg2rad($this->axis_text_angle_v))) +
          $this->axis_font_size + 2 * $this->axis_text_space;
      }

      if($this->show_axis_text_h) {
        // similar to vertical version
        $this->pad_bottom += ($this->axis_font_size * ($len_h - 1) *
          $this->axis_font_adjust *
          sin(deg2rad(abs($this->axis_text_angle_h)))) +
          $this->axis_font_size + 2 * $this->axis_text_space;
      }

      // make space for divisions
      $div_size = 0;
      if($this->show_divisions)
        $div_size = $this->division_size;
      if($this->show_subdivisions && $this->subdivision_size > $div_size)
        $div_size = $this->subdivision_size;

      $this->pad_bottom += $div_size;
      $this->pad_left += $div_size;
    }
    $this->label_adjust_done = true;
  }

  /**
   * Sets up grid width and height to fill padded area
   */
  protected function SetGridDimensions()
  {
    $this->g_height = $this->height - $this->pad_top - $this->pad_bottom;
    $this->g_width = $this->width - $this->pad_left - $this->pad_right;
  }

  /**
   * Calculates the effect of axes, applying to padding
   *  h_by_count = use the number of values instead of min/max
   *  bar        = bar graph mode (0 to right of origin)
   */
  protected function CalcAxes($h_by_count = false, $bar = false)
  {
    if($this->axes_calc_done)
      return;

    // sanitise grid divisions
    if(is_numeric($this->grid_division_v) && $this->grid_division_v <= 0)
      $this->grid_division_v = null;
    if(is_numeric($this->grid_division_h) && $this->grid_division_h <= 0)
      $this->grid_division_h = null;

    $v_max = $this->GetMaxValue();
    $v_min = $this->GetMinValue();
    $k_max = $this->GetMaxKey();
    $k_min = $this->GetMinKey();

    // check guides
    $this->CalcGuidelines();
    if(!is_null($this->max_guide['y']))
      $v_max = max($v_max, $this->max_guide['y']);
    if(!is_null($this->min_guide['y']))
      $v_min = min($v_min, $this->min_guide['y']);
    if(!is_null($this->max_guide['x']))
      $k_max = max($k_max, $this->max_guide['x']);
    if(!is_null($this->min_guide['x']))
      $k_min = min($k_min, $this->min_guide['x']);

    // validate axes
    if((is_numeric($this->axis_max_h) && is_numeric($this->axis_min_h) &&
      $this->axis_max_h <= $this->axis_min_h) ||
      (is_numeric($this->axis_max_v) && is_numeric($this->axis_min_v) &&
      $this->axis_max_v <= $this->axis_min_v))
        throw new Exception('Invalid axes specified');
    if((is_numeric($this->axis_max_h) &&
      ($this->axis_max_h < ($this->flip_axes ? $v_min : $k_min))) ||
      (is_numeric($this->axis_min_h) &&
      ($this->axis_min_h >= ($this->flip_axes ? $v_max : $k_max+1))) ||
      (is_numeric($this->axis_max_v) &&
      ($this->axis_max_v < ($this->flip_axes ? $k_min : $v_min))) ||
      (is_numeric($this->axis_min_v) &&
      ($this->axis_min_v >= ($this->flip_axes ? $k_max+1 : $v_max))))
        throw new Exception('No values in grid range');

    // if fixed grid spacing is specified, make the min spacing 1 pixel
    if(is_numeric($this->grid_division_v))
      $this->minimum_grid_spacing_v = 1;
    if(is_numeric($this->grid_division_h))
      $this->minimum_grid_spacing_h = 1;

    if(!$this->label_adjust_done)
      $this->LabelAdjustment($v_max, $this->GetLongestKey());

    if(is_null($this->g_height) || is_null($this->g_width))
      $this->SetGridDimensions();

    $x_max = $h_by_count ? $this->GetHorizontalCount() - 1 : max(0, $k_max);
    $x_min = $h_by_count ? 0 : min(0, $k_min);
    $y_max = max(0, $v_max);
    $y_min = min(0, $v_min);
    $x_len = $this->g_width;
    $y_len = $this->g_height;
    $bar_h = $bar_v = null;

    $max_h = $this->axis_max_h;
    $min_h = $this->axis_min_h;
    $max_v = $this->axis_max_v;
    $min_v = $this->axis_min_v;

    if($this->flip_axes) {

      if(!is_numeric($max_h)) $max_h = $y_max;
      if(!is_numeric($min_h)) $min_h = $y_min;
      if(!is_numeric($max_v)) $max_v = $x_max;
      if(!is_numeric($min_v)) $min_v = $x_min;

      $x_min_unit = 0;
      $x_fit = false;
      $y_min_unit = 1;
      $y_fit = true;
      $bar_v = $bar;

    } else {

      if(!is_numeric($max_h)) $max_h = $x_max;
      if(!is_numeric($min_h)) $min_h = $x_min;
      if(!is_numeric($max_v)) $max_v = $y_max;
      if(!is_numeric($min_v)) $min_v = $y_min;

      $x_min_unit = 1;
      $x_fit = true;
      $y_min_unit = 0;
      $y_fit = false;
      $bar_h = $bar;
    }

    if(!is_numeric($this->grid_division_h))
      $x_axis = new Axis($x_len, $max_h, $min_h, $x_min_unit, $x_fit);
    else
      $x_axis = new AxisFixed($x_len, $max_h, $min_h, $this->grid_division_h);

    if(!is_numeric($this->grid_division_v))
      $y_axis = new Axis($y_len, $max_v, $min_v, $y_min_unit, $y_fit);
    else
      $y_axis = new AxisFixed($y_len, $max_v, $min_v, $this->grid_division_v);

    if(!is_numeric($this->minimum_grid_spacing_h))
      $this->minimum_grid_spacing_h = $this->minimum_grid_spacing;
    if(!is_numeric($this->minimum_grid_spacing_v))
      $this->minimum_grid_spacing_v = $this->minimum_grid_spacing;
    $this->h_grid = $x_axis->Grid($this->minimum_grid_spacing_h, $bar_h);
    $this->v_grid = $y_axis->Grid($this->minimum_grid_spacing_v, $bar_v);

    $this->x0 = $x_axis->Zero();
    $this->y0 = $y_axis->Zero();
    $this->uneven_x = $x_axis->Uneven();
    $this->uneven_y = $y_axis->Uneven();

    $this->bar_unit_width = $x_axis->Unit();
    $this->bar_unit_height = $y_axis->Unit();

    $this->axis_width = $this->g_width;
    $this->axis_height = $this->g_height;

    if($this->show_subdivisions) {
      $this->sub_y = $this->FindSubdiv($this->v_grid, $this->bar_unit_height,
        $this->minimum_subdivision, $y_min_unit, $this->subdivision_v);
      $this->sub_x = $this->FindSubdiv($this->h_grid, $this->bar_unit_width,
        $this->minimum_subdivision, $x_min_unit, $this->subdivision_h);
    }

    $this->axes_calc_done = true;
  }


  /**
   * Find the subdivision size
   */
  protected function FindSubdiv($grid_div, $u, $min, $min_unit, $fixed)
  {
    if(!is_numeric($fixed)) {

      $D = $grid_div / $u;  // D = actual division size
      $min = max($min, $min_unit * $u); // use the larger minimum value

      // can we subdivide at all?
      if($grid_div / 2 < $min || $D <= $min_unit)
        return null;

      // find significant digits
      $d1 = sprintf('%0.f', $D);
      $count = ltrim($d1, '0.');
      while(strlen($count) > 2 && $count % 100 == 0)
        $count /= 10;

      $div = $grid_div / $count;
      if($div < $min) {

        // try to find a factor
        $start = floor($count / 2);
        $end = floor(sqrt((float)$count));
        for($f = floor($count/2); $f >= $end; --$f) {
          if($count % $f == 0) {
            $scount = $count / $f;
            $div1 = $grid_div / $scount;
            $div2 = $grid_div / $f;

            if($div1 >= $min && $div2 >= $min)
              return min($div1, $div2);
            if($div1 >= $min)
              return $div1;
            if($div2 >= $min)
              return $div2;
          }
        }
        return null;
      }
      return $div;

    } else {
      return $u * $fixed;
    }
  }


  /**
   * Calculates the position of grid lines
   */
  protected function CalcGrid()
  {
    if(isset($this->y_points))
      return;

    $grid_bottom = $this->height - $this->pad_bottom;
    $grid_top = $this->pad_top;
    $grid_left = $this->pad_left;
    $grid_right = $this->width - $this->pad_right;
    $this->y_points = array();
    $this->x_points = array();
    $this->y_subdivs = array();
    $this->x_subdivs = array();

    // keys are converted to strings to make them work
    $c = $y = 0;
    $yd = $this->v_grid / 2.0;

    while($y < $this->axis_height + $yd) {
      $ypoint = $this->NumString(($y - $this->y0) / $this->bar_unit_height);
      $this->y_points[$ypoint] = $grid_bottom - $y;
      ++$c;
      $s = $y + $this->sub_y;
      $y = $c * $this->v_grid;
      if($this->sub_y) {
        while($s < $this->axis_height && $s < $y) {
          $this->y_subdivs[] = $grid_bottom - $s;
          $s += $this->sub_y;
        }
      }
    } 

    $c = $x = 0;
    $xd = $this->h_grid / 2.0;
    while($x < $this->axis_width + $xd) {
      $xpoint = $this->NumString(($x - $this->x0) / $this->bar_unit_width);
      $this->x_points[$xpoint] = $grid_left + $x;
      ++$c;
      $s = $x + $this->sub_x;
      $x = $c * $this->h_grid;
      if($this->sub_x) {
        while($s < $this->axis_width && $s < $x) {
          $this->x_subdivs[] = $grid_left + $s;
          $s += $this->sub_x;
        }
      }
    } 
    // prime numbers can cause trouble
    if($this->uneven_x) {
      $x = $grid_right - $grid_left - $this->x0;
      $xpoint = $this->NumString($x / $this->bar_unit_width);
      $this->x_points[$xpoint] = $grid_right;
    }
    if($this->uneven_y) {
      $y = $grid_bottom - $grid_top - $this->y0;
      $ypoint = $this->NumString($y / $this->bar_unit_height);
      $this->y_points[$ypoint] = $grid_top;
    }
  }

  /**
   * Converts number to string
   */
  protected function NumString($n)
  {
    // subtract number of digits before decimal point from precision
    $d = is_int($n) ? 0 : ($this->precision - floor(log(abs($n))));
    $s = number_format($n, $d);

    if($d && strpos($s, '.') !== false) {
      list($a, $b) = explode('.', $s);
      $b1 = rtrim($b, '0');
      if($b1 != '')
        return "$a.$b1";
      return $a;
    }
    return $s;
  }


  /**
   * Subclasses can override this for non-linear graphs
   */
  protected function GetHorizontalCount()
  {
    $values = $this->GetValues();
    return count($values);
  }

  /**
   * Returns the key that takes up the most space
   */
  protected function GetLongestKey()
  {
    $longest_key = '';
    if($this->show_axis_text_v) {
      $max_len = 0;
      foreach($this->values[0] as $k => $v) {
        if(is_numeric($k))
          $k = $this->NumString($k);
        $len = strlen($k);
        if($len > $max_len) {
          $max_len = $len;
          $longest_key = $k;
        }
      }
    }
    return $longest_key;
  }

  /**
   * Returns the X axis SVG fragment
   */
  protected function XAxis($yoff)
  {
    $points = array();
    $points['x1'] = $this->pad_left - $this->axis_overlap;
    $points['x2'] = $this->width - $this->pad_right + $this->axis_overlap;
    $points['y1'] = $points['y2'] = 
      $this->height - $this->pad_bottom - $yoff;
    return $this->Element('line', $points);
  }

  /**
   * Returns the Y axis SVG fragment
   */
  protected function YAxis($xoff)
  {
    $points['x1'] = $points['x2'] = $this->pad_left + $xoff;
    $points['y1'] = $this->pad_top - $this->axis_overlap;
    $points['y2'] = $this->height - $this->pad_bottom + $this->axis_overlap;
    return $this->Element('line', $points);
  }

  /**
   * Returns X-axis divisions as a path
   */
  protected function XAxisDivisions(&$points, $size, $yoff)
  {
    $path = '';
    $y = $this->height - $this->pad_bottom - $yoff;
    foreach($points as $x)
      $path .= "M$x {$y}v{$size}";
    return $path;
  }

  /**
   * Returns Y-axis divisions as a path
   */
  protected function YAxisDivisions(&$points, $size, $xoff)
  {
    $path = '';
    $x = $this->pad_left + $xoff - $size;
    foreach($points as $y)
      $path .= "M$x {$y}h{$size}";
    return $path;
  }

  /**
   * Returns the X-axis text fragment
   */
  protected function XAxisText(&$points, $xoff, $yoff, $angle)
  {
    $labels = '';
    $x_prev = -$this->width;
    $min_space = $this->minimum_grid_spacing_h;
    $count = count($points);
    $label_centre_x = $this->label_centre && !$this->flip_axes;
    $text = array(
      'y' => $this->height - $this->pad_bottom + $yoff +
        $this->axis_font_size + $this->axis_text_space
    );
    $p = 0;
    foreach($points as $label => $x) {
      $key = $this->flip_axes ? $label : $this->GetKey($label);
      if(strlen($key) > 0 && $x - $x_prev >= $min_space
         &&  (++$p < $count || !$label_centre_x)) {
        $text['x'] = $x + $xoff;
        if($angle != 0) {
          $rcx = $text['x'];
          $rcy = $text['y'] - ($this->axis_font_size / 2);
          $text['transform'] = "rotate($angle,$rcx,$rcy)";
        }
        $labels .= $this->Element('text', $text, NULL, $key);
      }
      $x_prev = $x;
    }
    if($angle == 0) {
      $tgroup = array('text-anchor' => 'middle');
    } else {
      $tgroup = array('text-anchor' => $this->axis_text_angle_h < 0 ?
        'end' : 'start');
    }
    return $this->Element('g', $tgroup, NULL, $labels);
  }

  /**
   * Returns the Y-axis text fragment
   */
  protected function YAxisText(&$points, $xoff, $yoff, $angle)
  {
    $labels = '';
    $y_prev = $this->height;
    $min_space = $this->minimum_grid_spacing_v;
    $text_centre = $this->axis_font_size * 0.3;
    $label_centre_y = $this->label_centre && $this->flip_axes;

    $text = array(
      'x' => $this->pad_left - $xoff - $this->axis_text_space
    );
    $count = count($points);
    $p = 0;
    foreach($points as $label => $y) {
      $key = $this->flip_axes ? $this->GetKey($label) : $label;

      if(strlen($key) && $y_prev - $y >= $min_space &&
        (++$p < $count || !$label_centre_y)) {
        $text['y'] = $y + $text_centre + $yoff;
        if($angle != 0) {
          $rcx = $text['x'] - ($this->axis_font_size / 2);
          $rcy = $text['y'] - ($this->axis_font_size / 2);
          $text['transform'] = "rotate($angle,$rcx,$rcy)";
        }
        $labels .= $this->Element('text', $text, NULL, $key);
      }
      $y_prev = $y;
    }
    return $this->Element('g', array('text-anchor' => 'end'), NULL, $labels);
  }

  /**
   * Returns the horizontal axis label
   */
  protected function HLabel(&$attribs)
  {
    if(empty($this->label_h))
      return '';

    $x = ($this->width - $this->pad_left - $this->pad_right) / 2 +
      $this->pad_left;
    $y = $this->height - $this->label_bottom_offset;
    $pos = array('x' => $x, 'y' => $y);
    return $this->Text($this->label_h, $this->label_font_size,
      array_merge($attribs, $pos));
  }

  /**
   * Returns the vertical axis label
   */
  protected function VLabel(&$attribs)
  {
    if(empty($this->label_v))
      return '';

    $x = $this->label_left_offset;
    $y = ($this->height - $this->pad_bottom - $this->pad_top) / 2 +
      $this->pad_top;
    $pos = array(
      'x' => $x,
      'y' => $y,
      'transform' => "rotate(270,$x,$y)",
    );
    return $this->Text($this->label_v, $this->label_font_size,
      array_merge($attribs, $pos));
  }

  /**
   * Returns the labels grouped with the provided axis division labels
   */
  protected function Labels($axis_text = '')
  {
    $labels = $axis_text;
    if(!empty($this->label_h) || !empty($this->label_v)) {
      $label_text = array('text-anchor' => 'middle');
      if($this->label_font != $this->axis_font)
        $label_text['font-family'] = $this->label_font;
      if($this->label_font_size != $this->axis_font_size)
        $label_text['font-size'] = $this->label_font_size;
      if($this->label_font_weight != 'normal')
        $label_text['font-weight'] = $this->label_font_weight;
      if(!empty($this->label_colour) &&
        $this->label_colour != $this->axis_text_colour)
        $label_text['fill'] = $this->label_colour;

      if(!empty($this->label_h)) {
        $label_text['y'] = $this->height - $this->label_bottom_offset;
        $label_text['x'] = $this->pad_left +
          ($this->width - $this->pad_left - $this->pad_right) / 2;
        $labels .= $this->Text($this->label_h, $this->label_font_size,
          $label_text);
      }

      $labels .= $this->VLabel($label_text);
    }

    if(!empty($labels)) {
      $font = array(
        'font-size' => $this->axis_font_size,
        'font-family' => $this->axis_font,
        'fill' => empty($this->axis_text_colour) ?
          $this->axis_colour : $this->axis_text_colour,
      );
      $labels = $this->Element('g', $font, NULL, $labels);
    }
    return $labels;
  }

  /**
   * Draws bar or line graph axes
   */
  protected function Axes()
  {
    if(!$this->show_axes)
      return $this->Labels();

    $this->CalcGrid();
    $x_axis_visible = $this->y0 >= 0 && $this->y0 < $this->g_height;
    $y_axis_visible = $this->x0 >= 0 && $this->x0 < $this->g_width;
    $yoff = $x_axis_visible ? $this->y0 : 0;
    $xoff = $y_axis_visible ? $this->x0 : 0;

    $axis_group = $axes = $label_group = $divisions = 
      $d_path = $sd_path = $axis_text = '';
    if($x_axis_visible)
      $axes .= $this->XAxis($yoff);
    if($y_axis_visible)
      $axes .= $this->YAxis($xoff);

    if($axes != '') {
      $line = array(
        'stroke-width' => $this->axis_stroke_width,
        'stroke' => $this->axis_colour
      );
      $axis_group = $this->Element('g', $line, NULL, $axes);
    }

    $x_offset = $y_offset = 0;
    if($this->label_centre) {
      if($this->flip_axes)
        $y_offset = -0.5 * $this->bar_unit_height;
      else
        $x_offset = 0.5 * $this->bar_unit_width;
    }

    arsort($this->y_points);
    asort($this->x_points);
    if($this->show_axis_text_v)
      $axis_text .= $this->YAxisText($this->y_points, $this->division_size,
        $y_offset, $this->axis_text_angle_v);
    if($this->show_axis_text_h)
      $axis_text .= $this->XAxisText($this->x_points, $x_offset,
        $this->division_size, $this->axis_text_angle_h);

    $label_group = $this->Labels($axis_text);

    if($this->show_divisions) {
      $d_path .= $this->YAxisDivisions($this->y_points,
        $this->division_size, $xoff);
      $sd_path .= $this->YAxisDivisions($this->y_subdivs,
        $this->subdivision_size, $xoff);
      $d_path .= $this->XAxisDivisions($this->x_points,
        $this->division_size, $yoff);
      $sd_path .= $this->XAxisDivisions($this->x_subdivs,
        $this->subdivision_size, $yoff);

      $colour = empty($this->division_colour) ? $this->axis_colour :
        $this->division_colour;
      if(!$this->show_subdivisions || empty($this->subdivision_colour) ||
        $this->subdivision_colour == $colour) {
        $div = array(
          'd' => $d_path . $sd_path,
          'stroke-width' => 1,
          'stroke' => $colour
        );
        $divisions = $this->Element('path', $div);
      } else {
        $div = array(
          'd' => $d_path,
          'stroke-width' => 1,
          'stroke' => $colour
        );
        $divisions = $this->Element('path', $div);
        if($sd_path != '') {
          $sdiv = array(
            'd' => $sd_path,
            'stroke-width' => 1,
            'stroke' => $this->subdivision_colour
          );
          $divisions .= $this->Element('path', $sdiv);
        }
      }
    }
    return $divisions . $axis_group . $label_group;
  }

  /**
   * Draws the grid behind the bar / line graph
   */
  protected function Grid()
  {
    $this->CalcAxes();
    if(!$this->show_grid)
      return '';

    $x1 = $this->pad_left;
    $x2 = $this->width - $this->pad_right;
    $y1 = $this->height - $this->pad_bottom;
    $y2 = $this->pad_top;

    $this->CalcGrid();
    $subpath = $path = '';
    if($this->show_grid_subdivisions) {
      foreach($this->y_subdivs as $y) 
        $subpath .= "M$x1 {$y}L$x2 $y";
      foreach($this->x_subdivs as $x) 
        $subpath .= "M$x {$y1}L$x $y2";
      if($subpath != '') {
        $opts = array(
          'd' => $subpath,
          'stroke' => $this->grid_subdivision_colour
        );
        $subpath = $this->Element('path', $opts);
      }
    }
    foreach($this->y_points as $y) 
      $path .= "M$x1 {$y}L$x2 $y";
    foreach($this->x_points as $x) 
      $path .= "M$x {$y1}L$x $y2";

    $opts = array('d' => $path, 'stroke' => $this->grid_colour);
    $path = $this->Element('path', $opts);
    return $subpath . $path;
  }

  /**
   * clamps a value to the grid boundaries
   */
  protected function ClampVertical($val)
  {
    return max($this->pad_top, min($this->height - $this->pad_bottom, $val));
  }

  protected function ClampHorizontal($val)
  {
    return max($this->pad_left, min($this->width - $this->pad_right, $val));
  }

  /**
   * Returns a clipping path for the grid
   */
  protected function ClipGrid(&$attr)
  {
    $rect = array(
      'x' => $this->pad_left, 'y' => $this->pad_top,
      'width' => $this->width - $this->pad_left - $this->pad_right,
      'height' => $this->height - $this->pad_top - $this->pad_bottom
    );
    $this->defs[] = $this->Element('clipPath', array('id' => 'clipGrid'), NULL,
      $this->Element('rect', $rect));
    $attr['clip-path'] = 'url(#clipGrid)';
  }

  /**
   * Returns the grid position for a bar or point, or NULL if not on grid
   * $key  = actual value array index
   * $ikey = integer position in array
   */
  protected function GridPosition($key, $ikey)
  {
    $position = null;
    $gkey = $this->AssociativeKeys() ? $ikey : $key;
    if($this->flip_axes) {
      $top = $this->label_centre ?
        $this->g_height - ($this->bar_unit_height / 2) : $this->g_height;
      $offset = $this->y0 + ($this->bar_unit_height * $gkey);
      if($offset >= 0 && floor($offset) <= $top)
        $position = $this->height - $this->pad_bottom - $offset;
    } else {
      $right_end = $this->label_centre ?
        $this->g_width - ($this->bar_unit_width / 2) : $this->g_width;
      $offset = $this->x0 + ($this->bar_unit_width * $gkey);
      if($offset >= 0 && floor($offset) <= $right_end)
        $position = $this->pad_left + $offset;
    }
    return $position;
  }

  /**
   * Converts guideline options to more useful member variables
   */
  protected function CalcGuidelines($g = null)
  {
    if(is_null($g)) {
      // no guidelines?
      if(empty($this->guideline) && $this->guideline !== 0)
        return;

      if(is_array($this->guideline) && count($this->guideline) > 1 &&
        !is_string($this->guideline[1])) {

        // array of guidelines
        foreach($this->guideline as $gl)
          $this->CalcGuidelines($gl);
        return;
      }

      // single guideline
      $g = $this->guideline;
    }

    if(!is_array($g))
      $g = array($g);

    $value = $g[0];
    $axis = (isset($g[2]) && ($g[2] == 'x' || $g[2] == 'y')) ? $g[2] : 'y';
    $above = isset($g['above']) ? $g['above'] : $this->guideline_above;
    $position = $above ? SVGG_GUIDELINE_ABOVE : SVGG_GUIDELINE_BELOW;
    $guideline = array(
      'value' => $value,
      'depth' => $position,
      'title' => isset($g[1]) ? $g[1] : '',
      'axis' => $axis
    );
    $lopts = $topts = array();
    $line_opts = array(
      'colour' => 'stroke',
      'dash' => 'stroke-dasharray',
      'stroke_width' => 'stroke-width',
    );
    $text_opts = array(
      'colour' => 'fill',
      'font' => 'font-family',
      'font_size' => 'font-size',
      'font_weight' => 'font-weight',
      'text_colour' => 'fill', // overrides 'colour' option from line
      'text_position' => 'text_position', // will be nulled later
      'text_padding' => 'text_padding',   // "
      'text_angle' => 'text_angle',       // "
    );
    foreach($line_opts as $okey => $opt)
      if(isset($g[$okey]))
        $lopts[$opt] = $g[$okey];
    foreach($text_opts as $okey => $opt)
      if(isset($g[$okey]))
        $topts[$opt] = $g[$okey];

    if(count($lopts))
      $guideline['line'] = $lopts;
    if(count($topts))
      $guideline['text'] = $topts;

    // update maxima and minima
    if(is_null($this->max_guide[$axis]) || $value > $this->max_guide[$axis])
      $this->max_guide[$axis] = $value;
    if(is_null($this->min_guide[$axis]) || $value < $this->min_guide[$axis])
      $this->min_guide[$axis] = $value;

    // can flip the axes now the min/max are stored
    if($this->flip_axes)
      $guideline['axis'] = ($guideline['axis'] == 'x' ? 'y' : 'x');

    $this->guidelines[] = $guideline;
  }

  /**
   * Returns the elements to draw the guidelines
   */
  protected function Guidelines($depth)
  {
    if(empty($this->guidelines))
      return '';

    // build all the lines at this depth (above/below) that use
    // global options as one path
    $d = $lines = $text = '';
    $path = array(
      'stroke' => $this->guideline_colour,
      'stroke-width' => $this->guideline_stroke_width,
      'stroke-dasharray' => $this->guideline_dash,
      'fill' => 'none'
    );
    $textopts = array(
      'font-family' => $this->guideline_font,
      'font-size' => $this->guideline_font_size,
      'font-weight' => $this->guideline_font_weight,
      'fill' => empty($this->guideline_text_colour) ?
        $this->guideline_colour : $this->guideline_text_colour,
    );

    foreach($this->guidelines as $line) {
      if($line['depth'] == $depth)
        $this->BuildGuideline($line, $lines, $text, $path, $d);
    }
    if(!empty($d)) {
      $path['d'] = $d;
      $lines .= $this->Element('path', $path);
    }

    if(!empty($text))
      $text = $this->Element('g', $textopts, null, $text);
    return $lines . $text;
  }

  /**
   * Adds a single guideline and its title to content
   */
  protected function BuildGuideline(&$line, &$lines, &$text, &$path, &$d)
  {
    $path_data = $this->GuidelinePath($line['axis'], $line['value'],
      $line['depth'], $x, $y, $w, $h);
    if(!isset($line['line'])) {
      // no special options, add to main path
      $d .= $path_data;
    } else {
      $line_path = array_merge($path, $line['line'], array('d' => $path_data));
      $lines .= $this->Element('path', $line_path);
    }
    if(!empty($line['title'])) {
      $text_pos = $this->guideline_text_position;
      $text_pad = $this->guideline_text_padding;
      $text_angle = $this->guideline_text_angle;
      if(isset($line['text'])) {
        $this->UpdateAndUnset($text_pos, $line['text'], 'text_position');
        $this->UpdateAndUnset($text_pad, $line['text'], 'text_padding');
        $this->UpdateAndUnset($text_angle, $line['text'], 'text_angle');
      }
      // very approximate!
      $text_h = $this->guideline_font_size * $this->CountLines($line['title']);
      $text_len = strlen($line['title']) * $text_h * 0.7;

      list($x, $y, $text_right) = Graph::RelativePosition(
        $text_pos, $y, $x, $y + $h, $x + $w,
        $text_len, $text_h, $text_pad, true);

      $t = array('x' => $x, 'y' => $y + $this->guideline_font_size);
      if($text_right)
        $t['text-anchor'] = 'end';
      if($text_angle != 0) {
        $rx = $x + $text_h/2;
        $ry = $y + $text_h/2;
        $t['transform'] = "rotate($text_angle,$rx,$ry)";
      }
      if(isset($line['text']))
        $t = array_merge($t, $line['text']);
      $text .= $this->Text($line['title'], $this->guideline_font_size, $t);
    }
  }

  /**
   * Creates the path data for a guideline and sets the dimensions
   */
  protected function GuidelinePath($axis, $value, $depth, &$x, &$y, &$w, &$h)
  {
    $y_axis_pos = $this->height - $this->pad_bottom - $this->y0;
    $x_axis_pos = $this->pad_left + $this->x0;

    if($axis == 'x') {
      $x = $x_axis_pos + ($value * $this->bar_unit_width);
      $y = $this->pad_top;
      $w = 0;
      $h = $this->axis_height;
    } else {
      $x = $this->pad_left;
      $y = $y_axis_pos - ($value * $this->bar_unit_height);
      $w = $this->axis_width;
      $h = 0;
    }
    return "M{$x} {$y}l{$w} {$h}";
  }

  /**
   * Updates $var with $array[$key] and removes it from array
   */
  protected function UpdateAndUnset(&$var, &$array, $key)
  {
    if(isset($array[$key])) {
      $var = $array[$key];
      unset($array[$key]);
    }
  }
}

