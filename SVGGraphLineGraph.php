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

require_once 'SVGGraphPointGraph.php';

/**
 * LineGraph - joined line, with axes and grid
 */
class LineGraph extends PointGraph {

  private $line_style;

  public function Draw()
  {
    $assoc = $this->AssociativeKeys();
    $this->CalcAxes($assoc);
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
    $y_axis_pos = $this->height - $this->pad_bottom - $this->y0;
    $ccount = count($this->colours);
    $path = '';
    if($this->fill_under) {
      $cmd = 'L';
      $attr['fill'] = $this->GetColour(0);
      if($this->fill_opacity < 1.0)
        $attr['fill-opacity'] = $this->fill_opacity;
    }

    $values = $this->GetValues();
    foreach($values as $key => $value) {
      $point_pos = $this->GridPosition($key, $bnum);
      if(!is_null($value) && !is_null($point_pos)) {
        $x = $point_pos;
        $y = $y_axis_pos - ($value * $this->bar_unit_height);

        if($this->fill_under && $path == '')
          $path = "M$x $y_axis_pos";
        $path .= "$cmd$x $y ";

        // no need to repeat same L command
        $cmd = $cmd == 'M' ? 'L' : '';
        $this->AddMarker($x, $y, $key, $value);
      }
      ++$bnum;
    }

    if($this->fill_under)
      $path .= "$cmd$x {$y_axis_pos}z";

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

  protected function CheckValues(&$values)
  {
    parent::CheckValues($values);

    if(count($values[0]) <= 1)
      throw new Exception('Not enough values for line graph');
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

    $y += $h/2;
    $attr = $this->line_style;
    $attr['d'] = "M$x {$y}l$w 0";
    return $this->Element('path', $attr) . $marker;
  }

}

