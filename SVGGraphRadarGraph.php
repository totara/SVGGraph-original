<?php
/**
 * Copyright (C) 2012 Graham Breach
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

require_once 'SVGGraphPointGraph.php';

/**
 * RadarGraph - a line graph that goes around in circles
 */
class RadarGraph extends PointGraph {

  private $line_style;
  private $fill_style;
  protected $xc;
  protected $yc;
  protected $radius;
  protected $grid_angles;
  protected $arad;
  private $pad_v_axis_label;

  // in the case of radar graphs, $label_centre means we want an axis that
  // ends at N points + 1
  protected $label_centre = true;

  protected function Draw()
  {
    $body = $this->Grid();

    $attr = array('stroke' => $this->stroke_colour, 'fill' => 'none');
    $dash = is_array($this->line_dash) ?
      $this->line_dash[0] : $this->line_dash;
    $stroke_width = is_array($this->line_stroke_width) ?
      $this->line_stroke_width[0] : $this->line_stroke_width;
    if(!is_null($dash))
      $attr['stroke-dasharray'] = $dash;
    $attr['stroke-width'] = $stroke_width <= 0 ? 1 : $stroke_width;

    $bnum = 0;
    $cmd = 'M';

    $path = '';
    if($this->fill_under) {
      $attr['fill'] = $this->GetColour(0);
      $this->fill_style = array(
        'fill' => $attr['fill'],
        'stroke' => $attr['fill']
      );
      if($this->fill_opacity < 1.0) {
        $attr['fill-opacity'] = $this->fill_opacity;
        $this->fill_style['fill-opacity'] = $this->fill_opacity;
      }
    }

    $values = $this->GetValues();
    foreach($values as $key => $value) {
      $point_pos = $this->GridPosition($key, $bnum);
      if(!is_null($value) && !is_null($point_pos)) {
        $val = $this->y0 + $value * $this->bar_unit_height;
        $angle = $this->arad + $point_pos / $this->g_height;
        $x = $this->xc + ($val * sin($angle));
        $y = $this->yc + ($val * cos($angle));

        $path .= "$cmd$x $y ";

        // no need to repeat same L command
        $cmd = $cmd == 'M' ? 'L' : '';
        $this->AddMarker($x, $y, $key, $value);
      }
      ++$bnum;
    }

    $path .= "z";

    $this->line_style = $attr;
    $attr['d'] = $path;
    $group = array();
    $this->ClipGrid($group);
    $body .= $this->Element('g', $group, NULL, $this->Element('path', $attr));
    $body .= $this->Axes();
    $body .= $this->CrossHairs();
    $body .= $this->DrawMarkers();
    return $body;
  }

  /**
   * Need at least two values and no negative values
   */
  protected function CheckValues(&$values)
  {
    parent::CheckValues($values);

    if(count($values[0]) < 2)
      throw new Exception('Not enough values for radar graph');
    if($this->GetMinValue() < 0)
      throw new Exception('Negative value for radar graph');
  }

  /**
   * Return line and marker for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    // single line graph only supports one set
    if($set > 0)
      return '';

    $marker = parent::DrawLegendEntry($set, $x, $y, $w, $h);

    $h1 = $h/2;
    $y += $h1;
    $line = $this->line_style;
    $line['d'] = "M$x {$y}l$w 0";
    $graph_line = $this->Element('path', $line);
    if($this->fill_under) {
      $fill = $this->fill_style;
      $fill['d'] = "M$x {$y}l$w 0 0 $h1 -$w 0z";
      $graph_line = $this->Element('path', $fill) . $graph_line;
    }
    return $graph_line . $marker;
  }

  /**
   * Finds the grid position for radar graphs, returns NULL if not on graph
   */
  protected function GridPosition($key, $ikey)
  {
    $gkey = $this->AssociativeKeys() ? $ikey : $key;
    $offset = $this->x0 + ($this->bar_unit_width * $gkey);
    if($offset >= 0 && $offset < $this->g_width)
      return $offset;
    return NULL;
  }

  /**
   * Find the bounding box of the axis text for given axis lengths
   */
  protected function FindAxisTextBBox($length_x, $length_y)
  {
    $this->xc = $length_x / 2;
    $this->yc = $length_y / 2;
    $diameter = min($length_x, $length_y);
    $length_y = $diameter / 2;
    $length_x = 2 * M_PI * $length_y;
    $this->radius = $length_y;

    $bbox = parent::FindAxisTextBBox($length_x, $length_y);

    // normalise the bounding box
    $w_half = ($bbox['max_x'] - $bbox['min_x']) / 2;
    $h_half = ($bbox['max_y'] - $bbox['min_y']) / 2;
    $bbox = array(
      'min_x' => $this->xc - $w_half,
      'max_x' => $this->xc + $w_half,
      'min_y' => $this->yc - $h_half,
      'max_y' => $this->yc + $h_half
    );
    return $bbox;
  }

  /**
   * Draws concentric Y grid lines
   */
  protected function YGrid(&$y_points)
  {
    $path = '';

    if($this->grid_straight) {
      foreach($y_points as $y) {
        $x1 = $this->xc + $y * sin($this->arad);
        $y1 = $this->yc + $y * cos($this->arad);
        $path .= "M$x1 {$y1}L";
        foreach($this->grid_angles as $a) {
          $x1 = $this->xc + $y * sin($a);
          $y1 = $this->yc + $y * cos($a);
          $path .= "$x1 $y1 ";
        }
        $path .= "z";
      }
    } else {
      foreach($y_points as $y) {
        $p1 = $this->xc - $y;
        $p2 = $this->xc + $y;
        $path .= "M$p1 {$this->yc}A $y $y 0 1 1 $p2 {$this->yc}";
        $path .= "M$p2 {$this->yc}A $y $y 0 1 1 $p1 {$this->yc}";
      }
    }
    return $path;
  }

  /**
   * Draws radiating X grid lines
   */
  protected function XGrid(&$x_points)
  {
    $path = '';
    foreach($x_points as $x) {
      $angle = $this->arad + $x / $this->radius;
      $p1 = $this->radius * sin($angle);
      $p2 = $this->radius * cos($angle);
      $path .= "M{$this->xc} {$this->yc}l$p1 $p2";
    }
    return $path;
  }

  /**
   * Draws the grid behind the graph
   */
  protected function Grid()
  {
    $this->CalcAxes();
    if(!$this->show_grid)
      return '';

    $xc = $this->xc;
    $yc = $this->yc;
    $r = $this->radius;

    $this->CalcGrid();
    $back = $subpath = '';
    $back_colour = $this->grid_back_colour;
    if(!empty($back_colour) && $back_colour != 'none') {
      if(is_array($back_colour)) {
        $gradient_id = $this->AddGradient($back_colour);
        $back_colour = "url(#{$gradient_id})";
      }
      // use the YGrid function to get the path
      $points = array($r);
      $bpath = array(
        'd' => $this->YGrid($points),
        'fill' => $back_colour
      );
      $back = $this->Element('path', $bpath);
    }
    if($this->show_grid_subdivisions) {
      $subpath_h = $this->YGrid($this->y_subdivs);
      $subpath_v = $this->XGrid($this->x_subdivs);
      if($subpath_h != '' || $subpath_v != '') {
        $colour_h = $this->GetFirst($this->grid_subdivision_colour_h,
          $this->grid_subdivision_colour, $this->grid_colour_h,
          $this->grid_colour);
        $colour_v = $this->GetFirst($this->grid_subdivision_colour_v,
          $this->grid_subdivision_colour, $this->grid_colour_v,
          $this->grid_colour);
        $dash_h = $this->GetFirst($this->grid_subdivision_dash_h,
          $this->grid_subdivision_dash, $this->grid_dash_h, $this->grid_dash);
        $dash_v = $this->GetFirst($this->grid_subdivision_dash_v,
          $this->grid_subdivision_dash, $this->grid_dash_v, $this->grid_dash);

        if($dash_h == $dash_v && $colour_h == $colour_v) {
          $subpath = $this->GridLines($subpath_h . $subpath_v, $colour_h,
            $dash_h, 'none');
        } else {
          $subpath = $this->GridLines($subpath_h, $colour_h, $dash_h, 'none') .
            $this->GridLines($subpath_v, $colour_v, $dash_v, 'none');
        }
      }
    }

    $path_v = $this->YGrid($this->y_points);
    $path_h = $this->XGrid($this->x_points);

    $colour_h = $this->GetFirst($this->grid_colour_h, $this->grid_colour);
    $colour_v = $this->GetFirst($this->grid_colour_v, $this->grid_colour);
    $dash_h = $this->GetFirst($this->grid_dash_h, $this->grid_dash);
    $dash_v = $this->GetFirst($this->grid_dash_v, $this->grid_dash);

    if($dash_h == $dash_v && $colour_h == $colour_v) {
      $path = $this->GridLines($path_v . $path_h, $colour_h, $dash_h, 'none');
    } else {
      $path = $this->GridLines($path_h, $colour_h, $dash_h, 'none') .
        $this->GridLines($path_v, $colour_v, $dash_v, 'none');
    }

    return $back . $subpath . $path;
  }

  /**
   * Sets the grid size as circumference x radius
   */
  protected function SetGridDimensions()
  {
    if(!isset($this->radius)) {
      $w = $this->width - $this->pad_left - $this->pad_right;
      $h = $this->height - $this->pad_top - $this->pad_bottom;
      $this->xc = $this->pad_left + $w / 2;
      $this->yc = $this->pad_top + $h / 2;
      $this->radius = min($w, $h) / 2;
    }
    $this->g_height = $this->radius;
    $this->g_width = 2 * M_PI * $this->radius;
  }

  /**
   * Calculate the extra details for radar axes
   */
  protected function CalcAxes($h_by_count = false, $bar = false)
  {
    parent::CalcAxes($h_by_count, $bar);
    $w = $this->width - $this->pad_left - $this->pad_right;
    $h = $this->height - $this->pad_top - $this->pad_bottom;
    $this->xc = $this->pad_left + $w / 2;
    $this->yc = $this->pad_top + $h / 2;
  }

  /**
   * Calculates the position of grid lines
   */
  protected function CalcGrid()
  {
    if(isset($this->y_points))
      return;
    $this->arad = (90 + $this->start_angle) * M_PI / 180;

    parent::CalcGrid();
    $grid_bottom = $this->height - $this->pad_bottom;
    $grid_left = $this->pad_left;

    // want only Y size, not actual position
    foreach($this->y_points as $point => $ygrid)
      $this->y_points[$point] = $grid_bottom - $ygrid;
    if($this->sub_y) {
      foreach($this->y_subdivs as $point => $ygrid)
        $this->y_subdivs[$point] = $grid_bottom - $ygrid;
    }

    // same with X, only want distance
    foreach($this->x_points as $point => $xgrid) {
      $new_x = $xgrid - $grid_left;
      $this->x_points[$point] = $new_x;
      $this->grid_angles[] = $this->arad + $new_x / $this->radius;
    }
    if($this->sub_x) {
      foreach($this->x_subdivs as $point => $xgrid) {
        $new_x = $xgrid - $grid_left;
        $this->x_subdivs[$point] = $new_x;
        $this->grid_angles[] = $this->arad + $new_x / $this->radius;
      }
    }
    // put the grid angles in order
    sort($this->grid_angles);
  }

  /**
   * The X-axis is wrapped around the graph
   */
  protected function XAxis($yoff)
  {
    if(!$this->show_x_axis)
      return '';

    // use the YGrid function to get the path
    $points = array($this->radius);
    $path = $this->YGrid($points);
    return $this->Element('path', array('d' => $path, 'fill' => 'none'));
  }

  /**
   * The Y-axis is at start angle
   */
  protected function YAxis($xoff)
  {
    $radius = $this->radius + $this->axis_overlap;
    $x1 = $radius * sin($this->arad);
    $y1 = $radius * cos($this->arad);
    $path = "M{$this->xc} {$this->yc}l$x1 $y1";
    return $this->Element('path', array('d' => $path, 'fill' => 'none'));
  }

  /**
   * Division marks around the graph
   */
  protected function XAxisDivisions(&$points, $style, $size, $yoff)
  {
    $r1 = $this->radius;
    $path = '';
    $pos = $this->DivisionsPositions($style, $size, $this->radius, 0, $yoff);
    if(is_null($pos))
      return '';
    $r1 = $this->radius - $pos['pos'];
    foreach($points as $p) {
      $a = $this->arad + $p / $this->radius;
      $x1 = $this->xc + $r1 * sin($a);
      $y1 = $this->yc + $r1 * cos($a);
      $x2 = -$pos['sz'] * sin($a);
      $y2 = -$pos['sz'] * cos($a);
      $path .= "M$x1 {$y1}l$x2 $y2";
    }
    return $path;
  }

  /**
   * Draws Y-axis divisions at whatever angle the Y-axis is
   */
  protected function YAxisDivisions(&$points, $style, $size, $xoff)
  {
    $path = '';
    $pos = $this->DivisionsPositions($style, $size, $size, 0, $xoff);
    if(is_null($pos))
      return '';

    $a = $this->arad + ($this->arad <= M_PI_2 ? - M_PI_2 : M_PI_2);
    $px = $pos['pos'] * sin($a);
    $py = $pos['pos'] * cos($a);
    $x2 = $pos['sz'] * sin($a);
    $y2 = $pos['sz'] * cos($a);
    $c = cos($this->arad);
    $s = sin($this->arad);
    foreach($points as $y) {
      $x1 = ($this->xc + $y * $s) + $px;
      $y1 = ($this->yc + $y * $c) + $py;
      $path .= "M$x1 {$y1}l$x2 $y2";
    }
    return $path;
  }

  /**
   * Returns the positions of the X-axis text
   */
  protected function XAxisTextPositions(&$points, $xoff, $yoff, $angle, $inside)
  {
    $positions = array();
    $r = $this->radius + $yoff + $this->axis_text_space;
    $text_centre = $this->axis_font_size * 0.3;
    $count = count($points);
    $p = 0;
    $font_size = $this->axis_font_size;
    $font_adjust = $this->axis_font_adjust;
    foreach($points as $label => $x) {
      $key = $this->GetKey($label);
      if(strlen($key) > 0 && ++$p < $count) {
        $a = $this->arad + $x / $this->radius;
        $s = sin($a);
        $c = cos($a);
        $x1 = $r * $s;
        $y1 = $r * $c - $text_centre;
        $position = array(
          'x' => $this->xc + $x1,
          'y' => $this->yc + $y1,
          // $c == +1 or -1 is a particular case: anchor on middle of text
          'text-anchor' => (pow($c, 2) == 1 ? 'middle' :
            ($x1 >= 0 ? 'start' : 'end')),
          'angle' => $a,
          'sin' => $s,
          'cos' => $c
        );
        $size = $this->TextSize((string)$key, $font_size, $font_adjust, $angle,
          $font_size);
        // $s == +1 or -1 is a particular case: vertically centre
        $lines = $this->CountLines($key);
        if(pow($s, 2) == 1)
          $position['y'] -= ($lines / 2 - 1) * $font_size;
        elseif($c < 0)
          $position['y'] -= ($lines - 1) * $font_size;
        else
          $position['y'] += $font_size;
        if($angle != 0) {
          $rcx = $position['x'];
          $rcy = $position['y'];
          if($c < 0)
            $rcy += $font_size;
          elseif(pow($s, 2) != 1)
            $rcy -= $font_size;
          $position['transform'] = "rotate($angle,$rcx,$rcy)";
        }
        // $c == -1 is particular too : XAxis text can bump YAxis texts
        if($c == -1 && $this->start_angle % 360 == 90)
          $position['y'] -= $font_size / 2;
        elseif($c == 1 && $this->start_angle % 360 == 270)
          $position['y'] += $font_size / 2;
        $position['text'] = $key;
        $position['w'] = $size[0];
        $position['h'] = $size[1];
        $positions[] = $position;
      }
    }
    return $positions;
  }

  /**
   * Text labels for the wrapped X-axis
   */
  protected function XAxisText(&$points, $xoff, $yoff, $angle)
  { 
    $inside = ('inside' == $this->GetFirst($this->axis_text_position_h,
      $this->axis_text_position));
    $positions = $this->XAxisTextPositions($points, $xoff, $yoff, $angle,
      $inside);
    $labels = '';
    foreach($positions as $pos) {
      $text = $pos['text'];
      unset($pos['w'], $pos['h'], $pos['text'], $pos['angle'], $pos['sin'],
        $pos['cos']);
      $labels .= $this->Text($text, $this->axis_font_size, $pos);
    }
    return $labels;
  }

  /**
   * Returns the positions of the Y-axis text
   */
  protected function YAxisTextPositions(&$points, $xoff, $yoff, $angle, $inside)
  {
    $positions = array();
    $labels = '';
    $c = cos($this->arad);
    $s = sin($this->arad);
    $a = $this->arad + ($s * $c > 0 ? - M_PI_2 : M_PI_2);
    $x2 = ($xoff + $this->axis_text_space) * sin($a);
    $y2 = ($xoff + $this->axis_text_space) * cos($a);
    $x3 = 0;
    $y3 = $c > 0 ? $this->axis_font_size : 0;
    $position = array('text-anchor' => $s < 0 ? 'start' : 'end');
    $font_size = $this->axis_font_size;
    $font_adjust = $this->axis_font_adjust;
    foreach($points as $key => $y) {
      if(strlen($key) > 0) {
        $x1 = $y * $s;
        $y1 = $y * $c;
        $position['x'] = $this->xc + $x1 + $x2 + $x3;
        $position['y'] = $this->yc + $y1 + $y2 + $y3;
        if($angle != 0) {
          $rcx = $position['x'];
          $rcy = $position['y'];
          $position['transform'] = "rotate($angle,$rcx,$rcy)";
        }
        $size = $this->TextSize((string)$key, $font_size, $font_adjust, $angle,
          $font_size);
        $position['text'] = $key;
        $position['w'] = $size[0];
        $position['h'] = $size[1];
        $positions[] = $position;
      }
    }
    return $positions;
  }

  /**
   * Text labels for the Y-axis
   */
  protected function YAxisText(&$points, $xoff, $yoff, $angle)
  { 
    $positions = $this->YAxisTextPositions($points, $xoff, $yoff, $angle, false);
    $labels = '';
    $anchor = $positions[0]['text-anchor'];
    foreach($positions as $pos) {
      $text = $pos['text'];
      unset($pos['w'], $pos['h'], $pos['text'], $pos['text-anchor']);
      $labels .= $this->Text($text, $this->axis_font_size, $pos);
    }
    return $this->Element('g', array('text-anchor' => $anchor), NULL, $labels);
  }


  /**
   * Returns what would be the vertical axis label
   */
  protected function VLabel(&$attribs)
  {
    if(empty($this->label_v))
      return '';

    $c = cos($this->arad);
    $s = sin($this->arad);
    $a = $this->arad + ($s * $c > 0 ? - M_PI_2 : M_PI_2);
    $offset = max($this->division_size * (int)$this->show_divisions,
      $this->subdivision_size * (int)$this->show_subdivisions) +
      $this->pad_v_axis_label + $this->label_space;
    $offset += ($c < 0 ? ($this->CountLines($this->label_v) - 1) : 1) *
      $this->label_font_size;

    $x2 = $offset * sin($a);
    $y2 = $offset * cos($a);
    $p = $this->radius / 2;
    $x = $this->xc + $p * sin($this->arad) + $x2;
    $y = $this->yc + $p * cos($this->arad) + $y2;
    $a = $s < 0 ? 180 - $this->start_angle : -$this->start_angle;
    $pos = array(
      'x' => $x,
      'y' => $y,
      'transform' => "rotate($a,$x,$y)",
    );
    return $this->Text($this->label_v, $this->label_font_size,
      array_merge($attribs, $pos));
  }

}

