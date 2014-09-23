<?php
/**
 * Copyright (C) 2009-2011 Graham Breach
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

	protected $aspect_ratio = 1.0;
	protected $sort = true;
	protected $reverse = false;
	protected $start_angle = 0;
	protected $show_labels = true;
	protected $show_label_amount = false;
	protected $show_label_percent = false;
	protected $label_colour = 'white';
	protected $label_back_colour = NULL;
	protected $label_font = 'sans-serif';
	protected $label_font_weight = 'bold';
	protected $label_font_size = '18'; // pixels
	protected $label_fade_in_speed = 0;
	protected $label_fade_out_speed = 0;
	protected $label_position = 0.75;

	// for internal use
	protected $x_centre;
	protected $y_centre;
	protected $radius_x;
	protected $radius_y;
	protected $s_angle;
	protected $calc_done;

	/**
	 * Calculates position of pie
	 */
	protected function Calc()
	{
		$bound_x1 = $this->pad_left;
		$bound_y1 = $this->pad_top;
		$bound_x2 = $this->width - $this->pad_right;
		$bound_y2 = $this->height - $this->pad_bottom;

		$w = $bound_x2 - $bound_x1;
		$h = $bound_y2 - $bound_y1;

		if($this->aspect_ratio == 'auto')
			$this->aspect_ratio = $h/$w;
		elseif($this->aspect_ratio <= 0)
			$this->aspect_ratio = 1.0;

		$this->x_centre = (($bound_x2 - $bound_x1) / 2) + $bound_x1;
		$this->y_centre = (($bound_y2 - $bound_y1) / 2) + $bound_y1;
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
	public function Draw()
	{
		if(!$this->calc_done)
			$this->Calc();
		$speed_in = $this->show_labels && $this->label_fade_in_speed ? $this->label_fade_in_speed / 100.0 : 0;
		$speed_out = $this->show_labels && $this->label_fade_out_speed ? $this->label_fade_out_speed / 100.0 : 0;

		// take a copy for sorting
		$values = $this->GetValues();
		$total = array_sum($values);

		$unit_slice = 2.0 * M_PI / $total;
		$ccount = count($this->colours);
		$vcount = count($values);
		$sub_total = 0.0;

		if($this->sort)
			arsort($values);
		$body = '';
		$labels = '';

		$slice = 0;
		foreach($values as $key => $value) {
			if(!$value)
				continue;
			++$slice;

			$angle1 = $sub_total * $unit_slice;
			$angle2 = ($sub_total + $value) * $unit_slice;

			// get the path (or whatever) for a pie slice
			$attr = array('fill' => $this->GetColour(($slice-1) % $ccount, true));
			if($this->show_tooltips)
				$this->SetTooltip($attr, $key, $value, !$this->compat_events);
	
			$t_style = NULL;
			if($this->show_labels) {
				$ac = $this->s_angle + ($sub_total + ($value * 0.5)) * $unit_slice;
				$xc = $this->label_position * $this->radius_x * cos($ac);
				$yc = ($this->reverse ? -1 : 1) * $this->label_position * $this->radius_y * sin($ac);

				$text['id'] = $this->NewID();
				if($this->label_fade_in_speed && $this->compat_events)
					$text['opacity'] = '0.0';
				$tx = $this->x_centre + $xc;
				$ty = $this->y_centre + $yc + ($this->label_font_size * 0.3);

				// display however many lines of label
				$parts = array($key);
				if($this->show_label_amount)
					$parts[] = $value;
				if($this->show_label_percent)
					$parts[] = ($value / $total) * 100.0 . '%';

				$x_offset = is_null($this->label_back_colour) ? $tx : 0;
				$string = $this->TextLines($parts, $x_offset, $this->label_font_size);

				if(!is_null($this->label_back_colour)) {
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
				$this->SetFader($attr, $speed_in, $speed_out, $text['id'], !$this->compat_events);
			$path = $this->GetSlice($angle1, $angle2, $attr);
			$body .= $this->GetLink($key, $path);

			$sub_total += $value;
		}

		// group the slices
		$attr = array();
		$this->SetStroke($attr, 'round');
		$body = $this->Element('g', $attr, NULL, $body);

		if($this->show_labels) {
			$label_group = array(
				'text-anchor' => 'middle',
				'font-size' => $this->label_font_size,
				'font-family' => $this->label_font,
				'font-weight' => $this->label_font_weight,
			);
			$labels = $this->Element('g', $label_group, NULL, $labels);
		}
		return $body . $labels;
	}

	/**
	 * Returns a single slice of pie
	 */
	protected function GetSlice($angle1, $angle2, &$attr)
	{
		$x1 = $y1 = $x2 = $y2 = 0;
		$angle1 += $this->s_angle;
		$angle2 += $this->s_angle;
		$this->CalcSlice($angle1, $angle2, $x1, $y1, $x2, $y2);
		if((string)$x1 == (string)$x2 && (string)$y1 == (string)$y2) {
			$attr['cx'] = $this->x_centre;
			$attr['cy'] = $this->y_centre;
			$attr['rx'] = $this->radius_x;
			$attr['ry'] = $this->radius_y;
			return $this->Element('ellipse', $attr);
		} else {
			$outer = ($angle2 - $angle1 > M_PI ? 1 : 0);
			$sweep = ($this->reverse ? 0 : 1);
			$attr['d'] = "M{$this->x_centre},{$this->y_centre} L$x1,$y1 A{$this->radius_x} {$this->radius_y} 0 $outer,$sweep $x2,$y2 z";
			return $this->Element('path', $attr);
		}
	}

	protected function CalcSlice($angle1, $angle2, &$x1, &$y1, &$x2, &$y2)
	{
		$x1 = ($this->radius_x * cos($angle1));
		$y1 = ($this->reverse ? -1 : 1) * ($this->radius_y * sin($angle1));
		$x2 = ($this->radius_x * cos($angle2));
		$y2 = ($this->reverse ? -1 : 1) * ($this->radius_y * sin($angle2));

		$x1 += $this->x_centre;
		$y1 += $this->y_centre;
		$x2 += $this->x_centre;
		$y2 += $this->y_centre;
	}

	/**
	 * Checks that the data are valid
	 */
	protected function CheckValues(&$values)
	{
		parent::CheckValues($values);
		$sum = 0;
		foreach($values[0] as $key => $val) {
			if($val < 0)
				throw new Exception('Negative value for pie chart');
			$sum += $val;
		}
		if($sum <= 0)
			throw new Exception('Empty pie chart');
	}

}

