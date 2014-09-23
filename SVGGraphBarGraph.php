<?php
/**
 * Copyright (C) 2009-2013 Graham Breach
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

require_once 'SVGGraphGridGraph.php';

class BarGraph extends GridGraph {

  protected $bar_styles = array();
  protected $label_centre = TRUE;

  protected function Draw()
  {
    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $bar_width = ($this->bar_space >= $this->bar_unit_width ? '1' : 
      $this->bar_unit_width - $this->bar_space);
    $bar_style = array();

    $bnum = 0;
    $bspace = $this->bar_space / 2;
    $ccount = count($this->colours);
    foreach($this->values[0] as $item) {

      // assign bar in the loop so it doesn't keep ID
      $bar = array('width' => $bar_width);
      $bar_pos = $this->GridPosition($item->key, $bnum);
      if(!is_null($item->value) && !is_null($bar_pos)) {
        $this->SetStroke($bar_style, $item);
        $bar['x'] = $bspace + $bar_pos;
        $this->Bar($item->value, $bar);

        if($bar['height'] > 0) {
          $bar_style['fill'] = $this->GetColour($item, $bnum % $ccount);

          if($this->show_tooltips)
            $this->SetTooltip($bar, $item, $item->value, null,
              !$this->compat_events && $this->show_bar_labels);
          $rect = $this->Element('rect', $bar, $bar_style);
          if($this->show_bar_labels)
            $rect .= $this->BarLabel($item, $bar);
          $body .= $this->GetLink($item, $item->key, $rect);

          $this->bar_styles[] = $bar_style;
        }
      }
      ++$bnum;
    }

    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Fills in the y-position and height of a bar
   * @param number $value bar value
   * @param array  &$bar  bar element array [out]
   * @param number $start bar start value
   * @return number unclamped bar position
   */
  protected function Bar($value, &$bar, $start = null)
  {
    if($start)
      $value += $start;

    $startpos = is_null($start) ? $this->OriginY() : $this->GridY($start);
    if(is_null($startpos))
      $startpos = $this->OriginY();
    $pos = $this->GridY($value);
    if(is_null($pos)) {
      $bar['height'] = 0;
    } else {
      $l1 = $this->ClampVertical($startpos);
      $l2 = $this->ClampVertical($pos);
      $bar['y'] = min($l1, $l2);
      $bar['height'] = abs($l1-$l2);
    }
    return $pos;
  }

  /**
   * Text labels in or above the bar
   */
  protected function BarLabel(&$item, &$bar, $offset_y = null)
  {
    $content = $item->Data('label');
    if(is_null($content))
      $content = $this->units_before_label . Graph::NumString($item->value) .
        $this->units_label;
    $font_size = $this->bar_label_font_size;
    $space = $this->bar_label_space;
    $x = $bar['x'] + ($bar['width'] / 2);
    $colour = $this->bar_label_colour;
    $acolour = $this->bar_label_colour_above;

    if(!is_null($offset_y)) {
      $y = $bar['y'] + $offset_y;
    } else {
      // find positions
      $pos = $this->bar_label_position;
      if(empty($pos))
        $pos = 'top';
      $top = $bar['y'] + $font_size + $space;
      $bottom = $bar['y'] + $bar['height'] - $space;
      if($top > $bottom)
        $pos = 'above';

      $swap = ($bar['y'] >= $this->height - $this->pad_bottom - $this->y_axis->Zero());
      switch($pos) {
      case 'above' :
        $y = $swap ? $bar['y'] + $bar['height'] + $font_size + $space :
          $bar['y'] - $space;
        if(!empty($acolour))
          $colour = $acolour;
        break;
      case 'bottom' :
        $y = $swap ? $top : $bottom;
        break;
      case 'centre' :
        $y = $bar['y'] + ($bar['height'] + $font_size) / 2;
        break;
      case 'top' :
      default :
        $y = $swap ? $bottom : $top;
        break;
      }
    }

    $text = array(
      'x' => $x,
      'y' => $y,
      'text-anchor' => 'middle',
      'font-family' => $this->bar_label_font,
      'font-size' => $font_size,
      'fill' => $colour,
    );
    if($this->bar_label_font_weight != 'normal')
      $text['font-weight'] = $this->bar_label_font_weight;
    return $this->Element('text', $text, NULL, $content);
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->bar_styles[$set]))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->bar_styles[$set]);
  }

}

