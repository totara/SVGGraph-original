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

define('SVGGRAPH_VERSION', 'SVGGraph 2.5.1');

class SVGGraph {

	private $width = 100;
	private $height = 100;
	private $settings = array();
	public $values = array();
	public $links = NULL;
	public $colours = NULL;

	public function __construct($w, $h, $settings = NULL)
	{
		$this->width = $w;
		$this->height = $h;
		if(is_array($settings))
			$this->settings = $settings;
	}

	public function Values($values)
	{
		if(is_array($values)) 
			$this->values = $values;
		else
			$this->values = func_get_args();
	}
	public function Links($links)
	{
		$this->links = func_get_args();
	}
	public function Colours($colours)
	{
		$this->colours = func_get_args();
	}


	/**
	 * Instantiate the correct class
	 */
	private function Setup($class)
	{
		// load the relevant class file
		if(!class_exists($class))
			include('SVGGraph' . $class . '.php');

		$g = new $class($this->width, $this->height, $this->settings);
		$g->Values($this->values);
		$g->Links($this->links);
		if(!is_null($this->colours))
			$g->colours = $this->colours;
		return $g;
	}

	/**
	 * Fetch the content
	 */
	public function Fetch($class, $header = TRUE)
	{
		$g = $this->Setup($class);
		return $g->Fetch($header);
	}

	/**
	 * Pass in the type of graph to display
	 */
	public function Render($class, $header = TRUE, $content_type = TRUE)
	{
		$g = $this->Setup($class);
		return $g->Render($header, $content_type);
	}
}

/**
 * Base class for all graph types
 */
abstract class Graph {
	protected $precision = 5;
	protected $back_colour = 'rgb(240,240,240)';
	protected $back_round = 0;
	protected $back_stroke_width = 1;
	protected $back_stroke_colour = 'rgb(0,0,0)';
	protected $back_image = null;
	protected $back_image_opacity = null;
	protected $back_image_width = '100%';
	protected $back_image_height = '100%';
	protected $back_image_top = 0;
	protected $back_image_left = 0;
	protected $back_image_mode = 'auto';
	protected $stroke_colour = 'rgb(0,0,0)';
	protected $stroke_width = 1;
	protected $show_tooltips = true;
	protected $tooltip_colour = 'black';
	protected $tooltip_stroke_width = 1;
	protected $tooltip_back_colour = '#ffffcc';
	protected $tooltip_font = 'sans-serif';
	protected $tooltip_font_weight = 'normal';
	protected $tooltip_font_size = 10;
	protected $tooltip_offset = 10;
	protected $tooltip_padding = 3;
	protected $tooltip_round = 0;
	protected $tooltip_shadow_opacity = 0.3;
	protected $compat_events = false;

	protected $pad_top = 10;
	protected $pad_bottom = 10;
	protected $pad_left = 10;
	protected $pad_right = 10;

	protected $values = array();
	protected $link_base = '';
	protected $link_target = '_blank';
	protected $links = array();

	protected $defs = array();
	protected $functions = array();
	protected $variables = array();
	protected $comments = array();
	protected $onload = false;
	protected $show_version = FALSE;
	protected $title;
	protected $description;
	protected $namespace = FALSE;
	protected $doctype = FALSE;

	protected $namespaces = array();

	public function __construct($w, $h, $settings = NULL)
	{
		$this->width = $w;
		$this->height = $h;
		if(is_array($settings))
			$this->Settings($settings);

		// set default colours
		$this->colours = explode(' ', $this->svg_colours);
		shuffle($this->colours);
		unset($this->svg_colours);
	}

	/**
	 * Sets the options
	 */
	public function Settings(&$settings)
	{
		foreach($settings as $key => $value)
			$this->{$key} = $value;
	}

	/**
	 * Sets the graph values
	 */
	public function Values($values)
	{
		$this->values = array();
		$v = func_get_args();
		if(count($v) == 1)
			$v = $v[0];
		if(is_array($v) && isset($v[0]) && is_array($v[0]))
			$this->values = $v;
		else
			$this->values[0] = $v;
	}

	/**
	 * Returns a row of values
	 */
	protected function GetValues($row = 0)
	{
		if(is_array($this->values[$row]))
			return $this->values[$row];
		
		return $this->values;
	}

	/**
	 * Returns the key value for an index, if associative
	 */
	protected function GetKey($index)
	{
		$k = array_keys($this->values[0]);

		// this works around a strange bug - if you just return the key at $index,
		// for a non-associative array it repeats some!
		if(is_int($k[0]))
			return $index;
		if(isset($k[$index])) {
			$index = (string)$index;
			$index = (int)$index;
			return $k[$index];
		}
		return NULL;
	}

	/**
	 * Returns the minimum value
	 */
	protected function GetMinValue()
	{
		if(is_array($this->values[0]))
			return min($this->values[0]);
		return min($this->values);
	}

	/**
	 * Returns the maximum value
	 */
	protected function GetMaxValue()
	{
		if(is_array($this->values[0]))
			return max($this->values[0]);
		return max($this->values);
	}

	/**
	 * Returns the maximum key value
	 */
	protected function GetMaxKey()
	{
		$k0 = $this->GetKey(0);
		if(is_numeric($k0))
			return max(array_keys($this->values[0]));

		// if associative, return the index of the last key
		return $this->GetHorizontalCount() - 1;
	}

	/**
	 * Returns the minimum key value
	 */
	protected function GetMinKey()
	{
		$k0 = $this->GetKey(0);
		if(is_numeric($k0))
			return min(array_keys($this->values[0]));
		return 0;
	}

	/**
	 * Sets the links from each item
	 */
	public function Links()
	{
		$args = func_get_args();
		$this->links = (is_array($args[0]) ? $args[0] : $args);
	}

	/**
	 * Draws the selected graph
	 */
	public function DrawGraph()
	{
		$group = array('clip-path' => "url(#canvas)");

		$contents = $this->Canvas();
		$contents .= $this->Draw();
		$body = $this->Element('g', $group, NULL, $contents);
		return $body;
	}

	/**
	 * This should be overridden by subclass!
	 */
	abstract protected function Draw();

	/**
	 * Displays the background image
	 */
	protected function BackgroundImage()
	{
		if(!$this->back_image)
			return '';
		$image = array(
			'width' => $this->back_image_width,
			'height' => $this->back_image_height,
			'x' => $this->back_image_left,
			'y' => $this->back_image_top,
			'xlink:href' => $this->back_image,
			'preserveAspectRatio' => ($this->back_image_mode == 'stretch' ? 'none' : 'xMinYMin')
		);
		$style = array();
		if($this->back_image_opacity)
			$style['opacity'] = $this->back_image_opacity;

		$contents = '';
		if($this->back_image_mode == 'tile') {
			$image['x'] = 0; $image['y'] = 0;
			$im = $this->Element('image', $image, $style);
			$pattern = array(
				'id' => 'bgimage',
				'width' => $this->back_image_width,
				'height' => $this->back_image_height,
				'x' => $this->back_image_left,
				'y' => $this->back_image_top,
				'patternUnits' => 'userSpaceOnUse'
			);
			// tiled image becomes a pattern to replace background colour
			$this->defs[] = $this->Element('pattern', $pattern, NULL, $im);
			$this->back_colour = 'url(#bgimage)';
		} else {
			$im = $this->Element('image', $image, $style);
			$contents .= $im;
		}
		return $contents;
	}

	/**
	 * Displays the background
	 */
	protected function Canvas()
	{
		$bg = $this->BackgroundImage();
		$canvas = array(
			'width' => '100%', 'height' => '100%',
			'fill' => $this->back_colour,
			'stroke-width' => 0
		);
		if($this->back_round)
			$canvas['rx'] = $canvas['ry'] = $this->back_round;
		if($bg == '' && $this->back_stroke_width) {
			$canvas['stroke-width'] = $this->back_stroke_width;
			$canvas['stroke'] = $this->back_stroke_colour;
		}
		$c_el = $this->Element('rect', $canvas);
		$this->defs[] = $this->Element('clipPath', array('id' => 'canvas'), NULL, $c_el);
		if($bg != '') {
			$c_el .= $bg;
			if($this->back_stroke_width) {
				$canvas['stroke-width'] = $this->back_stroke_width;
				$canvas['stroke'] = $this->back_stroke_colour;
				$canvas['fill'] = 'none';
				$c_el .= $this->Element('rect', $canvas);
			}
		}
		return $c_el;
	}

	/**
	 * Fits text to a box - text will be bottom-aligned
	 */
	protected function TextFit($text, $x, $y, $w, $h, $attribs = NULL, $styles = NULL)
	{
		$pos = array('onload' => "textFit(evt,$x,$y,$w,$h)");
		if(is_array($attribs))
			$pos = array_merge($attribs, $pos);
		$txt = $this->Element('text', $pos, $styles, $text);

		/** Uncomment to see the box
		$rect = array('x' => $x, 'y' => $y, 'width' => $w, 'height' => $h, 'fill' => 'none', 'stroke' => 'black');
		$txt .= $this->Element('rect', $rect);
		**/
		$this->AddFunction('textFit');
		return $txt;
	}

	/**
	 * Displays readable (hopefully) error message
	 */
	protected function ErrorText($error)
	{
		$text = array('x' => $this->pad_left, 'y' => $this->height - 3);
		$style = array(
			'font-family' => 'monospace',
			'font-size' => '12px',
			'font-weight' => 'bold',
		);
		
		$e = $this->ContrastText($text['x'], $text['y'], $error, 'blue',
			'white', $style);
		return $e;
	}

	/**
	 * Displays high-contrast text
	 */
	protected function ContrastText($x, $y, $text, $fcolour = 'black', $bcolour = 'white',
		$properties = NULL, $styles = NULL)
	{
		$props = array('transform' => 'translate(' . $x . ',' . $y . ')', 'fill' => $fcolour);
		if(is_array($properties))
			$props = array_merge($properties, $props);

		$bg = $this->Element('text', array('stroke-width' => '2px', 'stroke' => $bcolour), NULL, $text);
		$fg = $this->Element('text', NULL, NULL, $text);
		return $this->Element('g', $props, $styles, $bg . $fg);
	}
 
	/**
	 * Formats lines of text
	 */
	protected function TextLines($text, $x, $line_spacing)
	{
		$start_pos = - (count($text) - 1) / 2 * $line_spacing;
		$dy = $start_pos;

		$string = '';
		foreach($text as $line) {
			$string .= $this->Element('tspan', array('x' => $x, 'dy' => $dy), NULL, $line);
			if($dy == $start_pos)
				$dy = $line_spacing;
		}

		return $string;
	}

	/**
	 * Draws an element
	 */
	protected function Element($name, $attribs = NULL, $styles = NULL, $content = NULL)
	{
		// these properties require units to work well
		$require_units = array('stroke-width', 'stroke-dashoffset',
			'font-size', 'baseline-shift', 'kerning', 'letter-spacing',
			'word-spacing');

		if($this->namespace && strpos($name, ':') === FALSE)
			$name = 'svg:' . $name;
		$element = '<' . $name;
		if(is_array($attribs))
			foreach($attribs as $attr => $val) {

				// if units required, add px
				if(is_numeric($val)) {
					if(array_search($attr, $require_units) !== FALSE)
						$val .= 'px';
				} else {
					$val = htmlspecialchars($val);
				}
				$element .= ' ' . $attr . '="' . $val . '"';
			}

		if(is_array($styles)) {
			$element .= ' style="';
			foreach($styles as $attr => $val) {
				// check units again
				if(is_numeric($val)) {
					if(array_search($attr, $require_units) !== FALSE)
						$val .= 'px';
				} else {
					$val = htmlspecialchars($val);
				}
				$element .= $attr . ':' . $val . ';';
			}
			$element .= '"';
		}

		if(is_null($content))
			$element .= "/>\n";
		else
			$element .= '>' . $content . '</' . $name . ">\n";

		return $element;
	}

	/**
	 * Returns a link URL or NULL if none
	 */
	protected function GetLinkURL($key, $row = 0)
	{
		if(!is_array($this->links[$row]) || !isset($this->links[$row][$key]))
			return NULL;

		$link = $this->links[$row][$key];
		if(strpos($link,'//') === FALSE) // check for absolute links
			$link = $this->link_base . $link;

		return $link;
	}

	/**
	 * Retrieves a link
	 */
	protected function GetLink($key, $content, $row = 0)
	{
		$link = $this->GetLinkURL($key, $row);
		if(is_null($link))
			return $content;

		$link_attr = array('xlink:href' => $link, 'target' => $this->link_target);
		return $this->Element('a', $link_attr, NULL, $content);
	}

	/**
	 * Returns a colour reference
	 */
	protected function GetColour($key, $no_gradient = FALSE)
	{
		if(!isset($this->colours[$key]))
			return 'none';
		if(is_array($this->colours[$key]))
			if($no_gradient) // sometimes gradients look awful
				return $this->colours[$key][0];
			else
				return 'url(#gradient' . $key . ')';
		return $this->colours[$key];
	}

	/**
	 * Checks that the data are valid
	 */
	protected function CheckValues(&$values)
	{
		if(count($values) == 0 || count($values[0]) == 0)
			throw new Exception('No data');
	}

	/**
	 * Checks if the keys are associative
	 */
	protected function AssociativeKeys()
	{
		$values = $this->GetValues();
		foreach(array_keys($values) as $k)
			if(!is_integer($k))
				return true;
		return false;
	}

	/**
	 * Sets the stroke options for an element
	 */
	protected function SetStroke(&$attr, $line_join = null)
	{
		if($this->stroke_width > 0) {
			$attr['stroke'] = $this->stroke_colour;
			$attr['stroke-width'] = $this->stroke_width;
			if(!is_null($line_join))
				$attr['stroke-linejoin'] = $line_join;
		}
	}

	/**
	 * Creates a new ID for an element
	 */
	protected function NewID()
	{
		if(!isset($this->last_id))
			$this->last_id = 0;
		return 'e' . base_convert(++$this->last_id, 10, 36);
	}

	/**
	 * Adds one or more javascript functions
	 */
	protected function AddFunction($name)
	{
		$fns = func_get_args();
		if(count($fns) > 1) {
			foreach($fns as $fn)
				$this->AddFunction($fn);
			return;
		}
		if(isset($this->functions[$name]))
			return true;

		switch($name)
		{
		case 'setattr' :
			$fn = "function setattr(i,a,v){i.setAttributeNS(null,a,v)}\n";
			break;
		case 'getE' :
			$fn = "function getE(i){return document.getElementById(i)}\n";
			break;

		case 'textFit' :
			$this->AddFunction('setattr');
			$fn = <<<JAVASCRIPT
function textFit(evt,x,y,w,h) {
	var t = evt.target;
	var aw = t.getBBox().width;
	var ah = t.getBBox().height;
	var trans = '';
	var s = 1.0;
	if(aw > w)
		s = w / aw;
	if(s * ah > h)
		s = h / ah;
	if(s != 1.0)
		trans = 'scale(' + s + ') ';
	trans += 'translate(' + (x / s) + ',' + ((y + h) / s) +  ')';
	setattr(t, 'transform', trans);
}\n
JAVASCRIPT;
			break;

		// fadeIn, fadeOut are shortcuts to fader function
		case 'fadeIn' : $name = 'fader';
		case 'fadeOut' : $name = 'fader';
		case 'fader' :
			$this->AddFunction('getE');
			$this->InsertVariable('faders','',1); // insert empty object
			$this->InsertVariable('fader_itimer',null);
			$fn = <<<JAVASCRIPT
function fadeIn(e,i,s){fader(e,i,0,1,s)}
function fadeOut(e,i,s){fader(e,i,1,0,s)}
function fader(e,i,o1,o2,s) {
	faders[i] = { id: i, o_start: o1, o_end: o2, step: (o1 < o2 ? s : -s) };
	fader_itimer ||	(fader_itimer = setInterval(fade,50));
}
function fade() {
	var f,ff,t,o;
	for(f in faders) {
		ff = faders[f], t = getE(ff.id);
		o = (t.style.opacity == '' ? ff.o_start : t.style.opacity * 1);
		o += ff.step;
		t.style.opacity = o < .01 ? 0 : (o > .99 ? 1 : o);
		if((ff.step > 0 && o >= 1) || (ff.step < 0 && o <= 0))
			delete faders[f];
	}
}\n
JAVASCRIPT;
			break;

		case 'newel' :
			$this->AddFunction('setattr');
			$fn = <<<JAVASCRIPT
function newel(e,a){
	var ns='http://www.w3.org/2000/svg', ne=document.createElementNS(ns,e),i;
	for(i in a)
		setattr(ne, i, a[i]);
	return ne;
}\n
JAVASCRIPT;
			break;
		case 'newtext' :
			$fn = "function newtext(c){return document.createTextNode(c);}\n";
			break;
		case 'showhide' :
			$this->AddFunction('setattr');
			$fn = "function showhide(e,h){setattr(e,'visibility',h?'visible':'hidden');}\n";
			break;
		case 'finditem' :
			$fn = <<<JAVASCRIPT
function finditem(e,list) {
	var l = e.target.correspondingUseElement || e.target, t;
	while(!t && l.parentNode) {
		t = l.id && list[l.id]
		l = l.parentNode;
	}
	return t;
}\n
JAVASCRIPT;
			break;
		case 'tooltip' :
			$this->AddFunction('getE');
			$this->AddFunction('setattr');
			$this->AddFunction('newel');
			$this->AddFunction('showhide');
			if($this->tooltip_shadow_opacity) {
				$ttoffs = (2 - $this->tooltip_stroke_width/2) . 'px';
				$shadow = <<<JAVASCRIPT
		shadow = newel('rect',{
			fill: 'rgba(0,0,0,{$this->tooltip_shadow_opacity})',
			x:'{$ttoffs}',y:'{$ttoffs}',
			width:'10px',height:'10px',
			id: 'ttshdw',
			rx:'{$this->tooltip_round}px',ry:'{$this->tooltip_round}px'
		});
		tt.appendChild(shadow);
JAVASCRIPT;
			} else {
				$shadow = '';
			}
			$dpad = 2 * $this->tooltip_padding;
			$fn = <<<JAVASCRIPT
function tooltip(e,callback,on,param) {
	var tt = getE('tooltip'), rect = getE('ttrect'), shadow = getE('ttshdw'), offset = {$this->tooltip_offset},
		x = e.clientX + offset, y = e.clientY + offset, inner, brect, bw, bh, sw, sh, de = document.documentElement;
	if(on && !tt) {
		tt = newel('g',{id:'tooltip',visibility:'visible'});
		rect = newel('rect',{
			stroke: '{$this->tooltip_colour}',
			'stroke-width': '{$this->tooltip_stroke_width}px',
			fill: '{$this->tooltip_back_colour}',
			width:'10px',height:'10px',
			id: 'ttrect',
			rx:'{$this->tooltip_round}px',ry:'{$this->tooltip_round}px'
		});
{$shadow}
		tt.appendChild(rect);
		de.appendChild(tt);
	}
	tt && showhide(tt,on);
	inner = callback(e,tt,on,param);
	if(inner && on) {
		brect = inner.getBBox();
		bw = Math.ceil(brect.width + {$dpad});
		bh = Math.ceil(brect.height + {$dpad});
		setattr(rect, 'width', bw + 'px');
		setattr(rect, 'height', bh + 'px');
		if(shadow) {
			setattr(shadow, 'width', (bw + {$this->tooltip_stroke_width}) + 'px');
			setattr(shadow, 'height', (bh + {$this->tooltip_stroke_width}) + 'px');
		}
		if(de.width) {
			sw = de.width.baseVal.value;
			sh = de.height.baseVal.value;
		} else {
			sw = window.innerWidth;
			sh = window.innerHeight;
		}
		if(bw + x > sw)
			x = Math.max(e.clientX - offset - bw,0);
		if(bh + y > sh)
			y = Math.max(e.clientY - offset - bh,0);
	}
	on && setattr(tt,'transform','translate('+x+' '+y+')');
}\n
JAVASCRIPT;
			break;

		case 'texttt' :
			$this->AddFunction('getE');
			$this->AddFunction('setattr');
			$this->AddFunction('newel');
			$this->AddFunction('newtext');
			$tty = ($this->tooltip_font_size + $this->tooltip_padding) . 'px';
			$fn = <<<JAVASCRIPT
function texttt(e,tt,on,t){
	var ttt = getE('tooltiptext');
	if(on) {
		if(!ttt) {
			ttt = newel('text', {
				id: 'tooltiptext',
				fill: '{$this->tooltip_colour}',
				'font-size': '{$this->tooltip_font_size}px',
				'font-family': '{$this->tooltip_font}',
				'font-weight': '{$this->tooltip_font_weight}',
				x:'{$this->tooltip_padding}px',y:'{$tty}'
			});
			ttt.appendChild(newtext(t));
			tt.appendChild(ttt);
		} else {
			ttt.firstChild.data = t;
		}
	}
	ttt && showhide(ttt,on);
	return ttt;
}\n
JAVASCRIPT;
			break;
		case 'ttEvent' :
			$this->AddFunction('finditem');
			$this->AddFunction('init');
			$this->InsertVariable('initfns', null, 'ttEvt');
			$fn = <<<JAVASCRIPT
function ttEvt() {
	document.addEventListener && document.addEventListener('mousemove',function(e) {
		var t = finditem(e,tips);
		tooltip(e,texttt,t,t);
	},false);
}\n
JAVASCRIPT;
			break;
		case 'fadeEvent' :
			$this->AddFunction('getE');
			$this->AddFunction('init');
			$this->InsertVariable('initfns', null, 'fade');
			$fn = <<<JAVASCRIPT
function fade() {
	var f,f1,e,o;
	for(f in fades) {
		f1 = fades[f];
		if(f1.dir) {
			e = getE(f1.id);
			o = (e.style.opacity || fstart) * 1 + f1.dir;
			e.style.opacity = o < .01 ? 0 : (o > .99 ? 1 : o);
		}
	}
	setTimeout(fade,50);
}\n
JAVASCRIPT;
			break;
		case 'fadeEventIn' :
			$this->AddFunction('init');
			$this->AddFunction('finditem');
			$this->InsertVariable('initfns', null, 'fiEvt');
			$fn = <<<JAVASCRIPT
function fiEvt() {
	var f;
	for(f in fades)
		getE(fades[f].id).style.opacity = fstart;
	document.addEventListener && document.addEventListener('mouseover',function(e) {
		var t = finditem(e,fades);
		t && (t.dir = fistep);
	},false);
}\n
JAVASCRIPT;
			break;
		case 'fadeEventOut' :
			$this->AddFunction('init');
			$this->AddFunction('finditem');
			$this->InsertVariable('initfns', null, 'foEvt');
			$fn = <<<JAVASCRIPT
function foEvt() {
	document.addEventListener && document.addEventListener('mouseout',function(e) {
		var t = finditem(e,fades);
		t && (t.dir = fostep);
	},false);
}\n
JAVASCRIPT;
			break;
		case 'duplicate' :
			$this->AddFunction('getE');
			$this->AddFunction('newel');
			$this->AddFunction('init');
			$this->InsertVariable('initfns', null, 'initDups');
			$fn = <<<JAVASCRIPT
function duplicate(f,t) {
	var e = getE(f), g, a, p = e && e.parentNode;
	if(e) {
		while(p.parentNode && (p.tagName != 'g' || !p.getAttributeNS(null,'clip-path'))) {
			p.tagName == 'a' && (a = p);
			p = p.parentNode;
		}
		g = e.cloneNode(true);
		g.style.opacity = 0;
		e.id = t;

		if(a) {
			a = a.cloneNode(false);
			a.appendChild(g);
			g = a;
		}
		p.appendChild(g);
	}
}
function initDups() {
	for(var d in dups)
		duplicate(d,dups[d]);
}\n
JAVASCRIPT;
			break;
		case 'init' :
			$this->onload = true;
			$fn = <<<JAVASCRIPT
function init() {
	if(!document.addEventListener || !initfns)
		return;
	for(var f in initfns)
		eval(initfns[f] + '()');
}\n
JAVASCRIPT;
			break;
		default :
			return false;
		}

		$this->InsertFunction($name, $fn);
		return true;
	}

	/**
	 * Inserts a Javascript function into the list
	 */
	public function InsertFunction($name, $fn)
	{
		$this->functions[$name] = $fn;
	}

	/**
	 * Adds a Javascript variable
	 * - use $value:$more for assoc
	 * - use null:$more for array
	 */
	public function InsertVariable($var, $value, $more = null, $quote = true)
	{
		$q = $quote ? "'" : '';
		if(is_null($more))
			$this->variables[$var] = $q . $value . $q;
		elseif(is_null($value))
			$this->variables[$var][] = $q . $more . $q;
		else
			$this->variables[$var][$value] = $q . $more . $q;
	}

	/**
	 * Insert a comment into the Javascript section - handy for debugging!
	 */
	public function InsertComment($details)
	{
		$this->comments[] = $details;
	}

	/**
	 * Creates a linear gradient element
	 */
	private function MakeLinearGradient($id, $colours)
	{
		$stops = '';
		$direction = 'v';
		if(in_array($colours[count($colours)-1], array('h','v')))
			$direction = array_pop($colours);
		$x2 = $direction == 'v' ? 0 : '100%';
		$y2 = $direction == 'h' ? 0 : '100%';
		$gradient = array('id' => $id, 'x1' => 0, 'x2' => $x2, 'y1' => 0, 'y2' => $y2);

		$col_mul = 100 / (count($colours) - 1);
		foreach($colours as $pos => $colour) {
			$stop = array('offset' => round($pos * $col_mul) . '%', 'stop-color' => $colour);
			$stops .= $this->Element('stop', $stop);
		}

		return $this->Element('linearGradient', $gradient, NULL, $stops);
	}

	/**
	 * Returns TRUE if the code contains the specified gradient
	 */
	private function ContainsGradient(&$code, $gradient)
	{
		return strpos($code, 'gradient' . $gradient) !== FALSE;
	}

	/**
	 * Adds an inline event handler to an element's array
	 */
	protected function AddEventHandler(&$array, $evt, $code)
	{
		if(isset($array[$evt]))
			$array[$evt] .= ';' . $code;
		else
			$array[$evt] = $code;
	}

	/**
	 * Default tooltip contents are key and value, or whatever
	 * $key is if $value is not set
	 */
	protected function SetTooltip(&$element, $key, $value = null, $duplicate = false)
	{
		$this->AddFunction('tooltip','texttt');
		$text = $this->TooltipText($key, $value);
		if($this->compat_events) {
			$this->AddEventHandler($element, 'onmousemove', "tooltip(evt,texttt,true,'$text')");
			$this->AddEventHandler($element, 'onmouseout', "tooltip(evt,texttt,false,'')");
		} else {
			$id = isset($element['id']) ? $element['id'] : $this->NewID();
			$this->AddFunction('ttEvent');
			$this->InsertVariable('tips',$id,$text);
			$element['id'] = $id;
		}
		if($duplicate) {
			if(!isset($element['id']))
				$element['id'] = $this->NewID();
			$this->AddOverlay($element['id'], $this->NewID());
		}
	}

	/**
	 * Sets the text to use for a tooltip
	 */
	protected function TooltipText($key, $value = null)
	{
		if(is_null($value))
			return addslashes($key);
		return addslashes($key . ', ' . $value);
	}

	/**
	 * Sets the fader for an element
	 */
	protected function SetFader(&$element, $in, $out, $target = null, $duplicate = false)
	{
		if(!isset($element['id']))
			$element['id'] = $this->NewID();
		if(is_null($target))
			$target = $element['id'];
		$id = $duplicate ? $this->NewID() : $element['id'];
		if($this->compat_events) {

			if($in) {
				$this->AddFunction('fadeIn');
				$this->AddEventHandler($element, 'onmouseover', 'fadeIn(evt,"' . $target . '", ' . $in . ')');
			}
			if($out) {
				$this->AddFunction('fadeOut');
				$this->AddEventHandler($element, 'onmouseout', 'fadeOut(evt,"' . $target . '", ' . $out . ')');
			}
		} else {

			$this->AddFunction('fadeEvent');
			if($in) {
				$this->AddFunction('fadeEventIn');
				$this->InsertVariable('fistep', $in, null, false);
			}
			if($out) {
				$this->AddFunction('fadeEventOut');
				$this->InsertVariable('fostep', -$out, null, false);
			}
			if($duplicate)
				$this->InsertVariable('fades',$element['id'],"{id:'{$target}',dir:0}",false);
			else
				$this->InsertVariable('fades',$element['id'],"{id:'{$target}',dir:0}",false);
			$this->InsertVariable('fstart', $in ? 0 : 1, null, false);
		}
		if($duplicate)
			$this->AddOverlay($element['id'], $id);
	}

	protected function AddOverlay($from, $to)
	{
		$this->AddFunction('duplicate');
		$this->InsertVariable('dups',$from,$to);
	}

	/**
	 * Returns the SVG document
	 */
	public function Fetch($header = TRUE)
	{
		$content = '';
		if($header) {
			// '>' is with \n so as not to confuse syntax highlighting
			$content .= '<?xml version="1.0" standalone="no"?' . ">\n";
			if($this->doctype)
				$content .= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" ' .
				'"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
		}

		// set the precision - PHP default is 14 digits!
		$old_precision = ini_set('precision', $this->precision);

		// display title and description if available
		$heading = '';
		if($this->title)
			$heading .= $this->Element('title', NULL, NULL, $this->title);
		if($this->description)
			$heading .= $this->Element('desc', NULL, NULL, $this->description);

		try {
			$this->CheckValues($this->values);
			// get the body content from the subclass
			$body = $this->DrawGraph();
		} catch(Exception $e) {
			$body = $this->ErrorText($e->getMessage());
		}

		$variables = '';
		$functions = '';
		// insert Javascript variables
		if(count($this->variables)) {
			$vlist = array();
			foreach($this->variables as $name => $value) {
				$var = $name;
				if(is_array($value)) {
					if(isset($value[0]) && isset($value[count($value)-1])) {
						$var .= '=[' . implode(',',$value) . ']';
					} else {
						$vs = array();
						foreach($value as $k => $v)
							if($k)
								$vs[] = "$k:$v";

						$var .= '={' . implode(',',$vs) . '}';
					}
				} elseif(!is_null($value)) {
					$var .= "=$value";
				}
				$vlist[] = $var;
			}
			$variables = "var " . implode(', ', $vlist) . ";";
		}
		// comments can be stuck with the variables
		if(count($this->comments)) {
			foreach($this->comments as $c) {
				if(!is_string($c))
					$c = print_r($c,true);
				$variables .= "\n// " . str_replace("\n", "\n// ", $c);
			}
		}
		// insert selected Javascript functions
		if(count($this->functions)) {
			$functions = implode('', $this->functions);
		}
		if($variables != '' || $functions != '') {
			$script = array('type' => 'text/javascript');
			$heading .= $this->Element('script', $script, NULL,
				"<![CDATA[\n$variables\n$functions\n// ]]>");
		}

		// insert any gradients that are used
		foreach($this->colours as $key => $c)
			if(is_array($c) && $this->ContainsGradient($body, $key))
				$this->defs[] = $this->MakeLinearGradient('gradient' . $key, $c);

		// show defs and body content
		$heading .= $this->Element('defs', NULL, NULL, implode('', $this->defs));
		$svg = array(
			'width' => $this->width, 'height' => $this->height, 
			'version' => '1.1', 
			'xmlns:xlink' => 'http://www.w3.org/1999/xlink'
		);
		if($this->onload)
			$svg['onload'] = 'init()';

		if($this->namespace)
			$svg['xmlns:svg'] = "http://www.w3.org/2000/svg";
		else
			$svg['xmlns'] = "http://www.w3.org/2000/svg";

		// add any extra namespaces
		foreach($this->namespaces as $ns => $url)
			$svg['xmlns:' . $ns] = $url;

		// display version string
		if($this->show_version) {
			$text = array('x' => $this->pad_left, 'y' => $this->height - 3);
			$style = array(
				'font-family' => 'monospace', 'font-size' => '12px',
				'font-weight' => 'bold',
			);
			$body .= $this->ContrastText($text['x'], $text['y'], SVGGRAPH_VERSION,
				'blue', 'white', $style);
		}

		$content .= $this->Element('svg', $svg, NULL, $heading . $body);
		// replace PHP's precision
		ini_set('precision', $old_precision);

		return $content;
	}

	/**
	 * Renders the SVG document
	 */
	public function Render($header = TRUE, $content_type = TRUE)
	{
		try {
			$content = $this->Fetch($header);
			if($content_type)
				header('Content-type: image/svg+xml; charset=UTF-8');
			echo $content;
		} catch(Exception $e) {
			if($content_type)
				header('Content-type: image/svg+xml; charset=UTF-8');
			$this->ErrorText($e);
		}
	}

	private $svg_colours = "aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray grey green greenyellow honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum powderblue purple red rosybrown royalblue saddlebrown salmon sandybrown seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen";

}

