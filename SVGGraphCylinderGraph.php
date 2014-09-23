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

require_once 'SVGGraphBar3DGraph.php';

class CylinderGraph extends Bar3DGraph {

  protected $bar_styles = array();
  protected $label_centre = true;

  protected function Draw()
  {
    // make sure project_angle is in range
    if($this->project_angle <= 0)
      $this->project_angle = 1; // prevent divide by zero
    elseif($this->project_angle > 90)
      $this->project_angle = 90;

    $body = $this->Grid() . $this->Guidelines(SVGG_GUIDELINE_BELOW);

    $values = $this->GetValues();
    $block_width = $this->bar_unit_width - $this->bar_space;

    // make the top ellipse, set it as a symbol for re-use
    list($sx, $sy) = $this->Project(0, 0, $this->bar_unit_width);
    $top_id = $this->NewID();

    $ellipse = $this->FindEllipse($this->project_angle, $block_width);
    $r = -$this->project_angle / 2;
    $top = array(
      'id' => $top_id,
      'cx' => 0, 'cy' => 0,
      'rx' => $ellipse['a'], 'ry' => $ellipse['b'],
      'transform' => "rotate({$r})",
    );

    $this->defs[] = $this->Element('symbol', NULL, NULL,
      $this->Element('ellipse', $top));
    $top = array('xlink:href' => '#' . $top_id);

    // use the ellipse info to create the bottom arc
    $rr = deg2rad($r);
    $x1 = -($ellipse['x1'] * cos($rr) + $ellipse['y1'] * sin($rr));
    $y1 = -($ellipse['x1'] * sin($rr) - $ellipse['y1'] * cos($rr));
    $x2 = -2 * $x1;
    $y2 = -2 * $y1;
    $cyl_offset_x = $x1;
    $cyl_offset_y = $y1;
    $a = $ellipse['a'];
    $b = $ellipse['b'];
    $arc_path = "a$a $b $r 1 0 $x2 $y2";

    $bnum = 0;
    $ccount = count($this->colours);

    // translation for the whole cylinder
    list($tx, $ty) = $this->Project(0, 0, $this->bar_space / 2);
    $tx = ($this->bar_unit_width + $sx) / 2;
    $ty = $sy / 2;
    $group = array('transform' => "translate($tx,$ty)");

    // the gradient overlay
    $shade_gradient_id = is_array($this->depth_shade_gradient) ?
      $this->AddGradient($this->depth_shade_gradient) : 0;

    $bar = array();
    $bars = '';
    foreach($values as $key => $value) {
      $bar_pos = $this->GridPosition($key, $bnum);

      if(!is_null($value) && !is_null($bar_pos)) {
        $bar['x'] = $bar_pos;
        $this->Bar($value, $bar);

        $top['transform'] = "translate($bar[x],$bar[y])";
        $x = $bar['x'] + $cyl_offset_x;
        $y = $bar['y'] + $cyl_offset_y;
        $h = $bar['height'];
        $side = array('d' => "M{$x} {$y}v{$h}{$arc_path}v-{$h}z");
        $group['fill'] = $this->GetColour($bnum % $ccount);
        $top['fill'] = $this->GetColour($bnum % $ccount, TRUE);

        $cyl_top = $this->Element('use', $top);
        $cyl_side = $this->Element('path', $side);

        if(!empty($shade_gradient_id)) {
          $side['fill'] = "url(#{$shade_gradient_id})";
          $cyl_side .= $this->Element('path', $side);
        }
        $link = $this->GetLink($key, $cyl_side . $cyl_top);

        if($this->show_tooltips)
          $this->SetTooltip($group, $value);
        $bars .= $this->Element('g', $group, NULL, $link);
        unset($group['id']); // make sure a new one is generated
        $style = $group;
        $this->SetStroke($style);
        $this->bar_styles[] = $style;
      }
      ++$bnum;
    }

    $bgroup = array('fill' => 'none');
    $this->SetStroke($bgroup, 'round');
    $body .= $this->Element('g', $bgroup, NULL, $bars);
    $body .= $this->Guidelines(SVGG_GUIDELINE_ABOVE) . $this->Axes();
    return $body;
  }

  /**
   * Calculates the a and b radii of the ellipse filling the parallelogram
   */
  protected function FindEllipse($angle, $length)
  {
    $alpha = deg2rad($angle / 2);
    $x = $length * cos($alpha) / 2;
    $y = $length * sin($alpha) / 2;
    $dydx = -$y / $x;

    $bsq = pow($y, 2) - $x * $y * $dydx;
    $asq = pow($x, 2) / (1 - $y / ($y - $x * $dydx));

    $a = sqrt($asq);
    $b = sqrt($bsq);

    // now find the vertical
    $alpha2 = deg2rad(- $angle / 2 - 90);
    $dydx2 = tan($alpha2);
    $ysq = $bsq / (pow($dydx2, 2) * ($asq / $bsq) + 1);
    $xsq = $asq - $asq * $ysq / $bsq;

    $x1 = sqrt($xsq);
    $y1 = -sqrt($ysq);
    return compact('a', 'b', 'x1', 'y1');
  }
}

