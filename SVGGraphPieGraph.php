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

class PieGraph extends Graph {

  // for internal use
  protected $x_centre;
  protected $y_centre;
  protected $radius_x;
  protected $radius_y;
  protected $s_angle;
  protected $calc_done;
  protected $slice_styles = array();
  protected $total = 0;

  /**
   * Calculates position of pie
   */
  protected function Calc()
  {
    $bound_x_left = $this->pad_left;
    $bound_y_top = $this->pad_top;
    $bound_x_right = $this->width - $this->pad_right;
    $bound_y_bottom = $this->height - $this->pad_bottom;

    $w = $bound_x_right - $bound_x_left;
    $h = $bound_y_bottom - $bound_y_top;

    if($this->aspect_ratio == 'auto')
      $this->aspect_ratio = $h/$w;
    elseif($this->aspect_ratio <= 0)
      $this->aspect_ratio = 1.0;

    $this->x_centre = (($bound_x_right - $bound_x_left) / 2) + $bound_x_left;
    $this->y_centre = (($bound_y_bottom - $bound_y_top) / 2) + $bound_y_top;
    $this->start_angle %= 360;
    if($this->start_angle < 0)
      $this->start_angle = 360 + $this->start_angle;
    $this->s_angle = deg2rad($this->start_angle);

    if($h/$w > $this->aspect_ratio) {
      $this->radius_x = $w / 2.0;
      $this->radius_y = $this->radius_x * $this->aspect_ratio;
    } else {
      $this->radius_y = $h / 2.0;
      $this->radius_x = $this->radius_y / $this->aspect_ratio;
    }
    $this->calc_done = true;
  }

  /**
   * Draws the pie graph
   */
  protected function Draw()
  {
    if(!$this->calc_done)
      $this->Calc();
    $speed_in = $this->show_labels && $this->label_fade_in_speed ?
      $this->label_fade_in_speed / 100.0 : 0;
    $speed_out = $this->show_labels && $this->label_fade_out_speed ?
      $this->label_fade_out_speed / 100.0 : 0;

    $unit_slice = 2.0 * M_PI / $this->total;
    $ccount = count($this->colours);
    $vcount = 0;
    $sub_total = 0.0;

    // need to store the original position of each value, because the
    // sorted list must still refer to the relevant legend entries
    $position = 0;
    $values = array();
    foreach($this->values[0] as $item) {
      $values[$item->key] = array($position++, $item->value, $item);
      if(!is_null($item->value))
        ++$vcount;
    }
    if($this->sort)
      uasort($values, 'pie_rsort');

    $body = $labels = '';
    $slice = 0;
    $slices = array();
    foreach($values as $key => $value) {

      // get the original array position of the value
      $original_position = $value[0];
      $item = $value[2];
      $value = $value[1];
      if($this->legend_show_empty || $item->value != 0) {
        $attr = array('fill' => $this->GetColour($item, $slice % $ccount, true,
          true));
        $this->SetStroke($attr, $item, 0, 'round');

        // store the current style referenced by the original position
        $this->slice_styles[$original_position] = $attr;
        ++$slice;
      }

      if(!$value)
        continue;

      $angle_start = $sub_total * $unit_slice;
      $angle_end = ($sub_total + $value) * $unit_slice;

      if($this->show_tooltips)
        $this->SetTooltip($attr, $item, $key, $value, !$this->compat_events);
  
      $t_style = NULL;
      if($this->show_labels) {
        if($vcount > 1) {
          $ac = $this->s_angle + ($sub_total + ($value * 0.5)) * $unit_slice;
          $xc = $this->label_position * $this->radius_x * cos($ac);
          $yc = ($this->reverse ? -1 : 1) * $this->label_position *
            $this->radius_y * sin($ac);
        } else {
          $xc = $yc = 0;
        }

        $text['id'] = $this->NewID();
        if($this->label_fade_in_speed && $this->compat_events)
          $text['opacity'] = '0.0';
        $tx = $this->x_centre + $xc;
        $ty = $this->y_centre + $yc + ($this->label_font_size * 0.3);

        // display however many lines of label
        $label = $item->Data('label');
        if(is_null($label)) {
          $parts = array();
          if($this->show_label_key)
            $parts = explode("\n", $this->GetKey($this->values->AssociativeKeys() ? 
              $original_position : $key));
          if($this->show_label_amount)
            $parts[] = $this->units_before_label . Graph::NumString($value) .
              $this->units_label;
          if($this->show_label_percent)
            $parts[] = Graph::NumString($value / $this->total * 100.0,
              $this->label_percent_decimals) . '%';
        } else {
          $parts = array($label);
        }

        $x_offset = empty($this->label_back_colour) ? $tx : 0;
        $string = $this->TextLines($parts, $x_offset, $this->label_font_size);

        if(!empty($this->label_back_colour)) {
          $labels .= $this->ContrastText($tx, $ty, $string, 
            $this->label_colour, $this->label_back_colour, $text);
        } else {
          $text['x'] = $tx;
          $text['y'] = $ty;
          $text['fill'] = $this->label_colour;
          $labels .= $this->Element('text', $text, NULL, $string);
        }
      }
      if($speed_in || $speed_out)
        $this->SetFader($attr, $speed_in, $speed_out, $text['id'],
          !$this->compat_events);

      $this->CalcSlice($angle_start, $angle_end, $x1, $y1, $x2, $y2);
      $single_slice = ($vcount == 1) || 
        ((string)$x1 == (string)$x2 && (string)$y1 == (string)$y2 &&
          (string)$angle_start != (string)$angle_end);

      $path = $this->GetSlice($angle_start, $angle_end, $attr, $single_slice);
      $this_slice = $this->GetLink($item, $key, $path);
      if($single_slice)
        array_unshift($slices, $this_slice);
      else
        $slices[] = $this_slice;

      $sub_total += $value;
    }
    $body .= implode($slices);

    if($this->show_labels) {
      $label_group = array(
        'text-anchor' => 'middle',
        'font-size' => $this->label_font_size,
        'font-family' => $this->label_font,
        'font-weight' => $this->label_font_weight,
      );
      $labels = $this->Element('g', $label_group, NULL, $labels);
    }
    $extras = $this->PieExtras();
    return $body . $extras . $labels;
  }

  /**
   * Returns a single slice of pie
   */
  protected function GetSlice($angle_start, $angle_end, &$attr, $single_slice)
  {
    $x_start = $y_start = $x_end = $y_end = 0;
    $angle_start += $this->s_angle;
    $angle_end += $this->s_angle;
    $this->CalcSlice($angle_start, $angle_end, $x_start, $y_start,
      $x_end, $y_end);
    if($single_slice) {
      $attr['cx'] = $this->x_centre;
      $attr['cy'] = $this->y_centre;
      $attr['rx'] = $this->radius_x;
      $attr['ry'] = $this->radius_y;
      return $this->Element('ellipse', $attr);
    } else {
      $outer = ($angle_end - $angle_start > M_PI ? 1 : 0);
      $sweep = ($this->reverse ? 0 : 1);
      $attr['d'] = "M{$this->x_centre},{$this->y_centre} L$x_start,$y_start " .
        "A{$this->radius_x} {$this->radius_y} 0 $outer,$sweep $x_end,$y_end z";
      return $this->Element('path', $attr);
    }
  }

  protected function CalcSlice($angle_start, $angle_end,
    &$x_start, &$y_start, &$x_end, &$y_end)
  {
    $x_start = ($this->radius_x * cos($angle_start));
    $y_start = ($this->reverse ? -1 : 1) *
      ($this->radius_y * sin($angle_start));
    $x_end = ($this->radius_x * cos($angle_end));
    $y_end = ($this->reverse ? -1 : 1) *
      ($this->radius_y * sin($angle_end));

    $x_start += $this->x_centre;
    $y_start += $this->y_centre;
    $x_end += $this->x_centre;
    $y_end += $this->y_centre;
  }

  /**
   * Checks that the data are valid
   */
  protected function CheckValues()
  {
    parent::CheckValues();
    if($this->GetMinValue() < 0)
      throw new Exception('Negative value for pie chart');

    $sum = 0;
    foreach($this->values[0] as $item)
      $sum += $item->value;
    if($sum <= 0)
      throw new Exception('Empty pie chart');

    $this->total = $sum;
  }

  /**
   * Returns extra drawing code that goes between pie and labels
   */
  protected function PieExtras()
  {
    return '';
  }

  /**
   * Return box for legend
   */
  protected function DrawLegendEntry($set, $x, $y, $w, $h)
  {
    if(!isset($this->slice_styles[$set]))
      return '';

    $bar = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h);
    return $this->Element('rect', $bar, $this->slice_styles[$set]);
  }

}

/**
 *  Sort callback function reverse-sorts by value
 */
function pie_rsort($a, $b)
{
  return $b[1] - $a[1];
}

