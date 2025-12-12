<?php
if (!defined('ABSPATH')) { exit; }

class SAW_Email_Template {
	
	private $templates_dir;
	private $translations_cache = array();
	
	public function __construct() {
		$this->templates_dir = __DIR__ . '/templates/';
	}
	
	public function render_single($type, $language, $data, $customer) {
		$translations = $this->get_translations($type, $language);
		
		if (empty($translations)) {
			$translations = $this->get_translations($type, 'cs');
		}
		
		if (empty($translations)) {
			return new WP_Error('no_translations', "No translations found for: {$type}");
		}
		
		$content = $this->build_content($type, $translations, $data['placeholders'] ?? array());
		$body_html = $this->wrap_html($content, $customer, $language);
		$body_text = $this->html_to_text($body_html);
		
		return array(
			'subject'   => $content['subject'],
			'body_html' => $body_html,
			'body_text' => $body_text,
		);
	}
	
	public function render_dual($type, $data, $customer) {
    // Get translations for both languages
    $cs_translations = $this->get_translations($type, 'cs');
    $en_translations = $this->get_translations($type, 'en');
    
    if (empty($cs_translations)) {
        return new WP_Error('no_cs_translations', "No Czech translations for: {$type}");
    }
    
    if (empty($en_translations)) {
        return $this->render_single($type, 'cs', $data, $customer);
    }
    
    $placeholders = $data['placeholders'] ?? array();
    
    // Build content for both languages
    $cs_content = $this->build_content($type, $cs_translations, $placeholders);
    $en_content = $this->build_content($type, $en_translations, $placeholders);
    
    // Combine into dual-language HTML
    $body_html = $this->wrap_dual_html($cs_content, $en_content, $customer);
    
    // Subject - NOVÝ FORMÁT: "Firma - Česky / English"
    $cs_subject_text = $cs_content['subject'];
    $en_subject_text = $en_content['subject'];
    
    // Odstranit název firmy z jednotlivých subjects (pokud tam je)
    $customer_name = $customer['name'] ?? '';
    $cs_subject_clean = trim(str_replace(array($customer_name . ' - ', ' - ' . $customer_name, $customer_name), '', $cs_subject_text));
    $en_subject_clean = trim(str_replace(array($customer_name . ' - ', ' - ' . $customer_name, $customer_name), '', $en_subject_text));
    
    // Pokud jsou subjects stejné (bez firmy), použij jen jeden
    if ($cs_subject_clean === $en_subject_clean) {
        $subject = $customer_name . ' - ' . $cs_subject_clean;
    } else {
        $subject = $customer_name . ' - ' . $cs_subject_clean . ' / ' . $en_subject_clean;
    }
    
    // Plain text version
    $body_text = $this->build_dual_text($cs_content, $en_content);
    
    return array(
        'subject'   => $subject,
        'body_html' => $body_html,
        'body_text' => $body_text,
    );
}
	
	private function get_translations($type, $language) {
		$cache_key = "{$type}_{$language}";
		
		if (isset($this->translations_cache[$cache_key])) {
			return $this->translations_cache[$cache_key];
		}
		
		global $wpdb;
		
		$results = $wpdb->get_results($wpdb->prepare(
			"SELECT translation_key, translation_text 
			 FROM {$wpdb->prefix}saw_ui_translations 
			 WHERE context = 'email' 
			 AND section = %s 
			 AND language_code = %s",
			$type,
			$language
		), ARRAY_A);
		
		$translations = array();
		foreach ($results as $row) {
			$translations[$row['translation_key']] = $row['translation_text'];
		}
		
		$this->translations_cache[$cache_key] = $translations;
		
		return $translations;
	}
	
	private function build_content($type, $translations, $placeholders) {
    // Replace placeholders in all translations
    $processed = array();
    foreach ($translations as $key => $text) {
        $processed[$key] = $this->replace_placeholders($text, $placeholders);
    }
    
    // PŘIDAT: Raw placeholders pro template (URL, atd.)
    foreach ($placeholders as $key => $value) {
        if (!isset($processed[$key])) {
            $processed[$key] = $value;
        }
    }
    
    // Format dates if present
    if (!empty($placeholders['date_from'])) {
        $processed['date_formatted'] = date_i18n('d.m.Y', strtotime($placeholders['date_from']));
    }
    if (!empty($placeholders['date_to'])) {
        $to_date = date_i18n('d.m.Y', strtotime($placeholders['date_to']));
        if (isset($processed['date_formatted']) && $processed['date_formatted'] !== $to_date) {
            $processed['date_formatted'] .= ' - ' . $to_date;
        }
    }
    
    return array(
        'subject' => $processed['subject'] ?? '',
        'greeting' => $processed['greeting'] ?? '',
        'body' => $this->build_body($type, $processed),
        'footer' => $processed['footer'] ?? '',
    );
}
	
	private function build_body($type, $processed) {
		$template_file = $this->templates_dir . $type . '.php';
		
		if (file_exists($template_file)) {
			ob_start();
			$t = $processed;
			include $template_file;
			return ob_get_clean();
		}
		
		// Fallback
		$body = '';
		
		if (!empty($processed['intro'])) {
			$body .= '<p>' . nl2br(esc_html($processed['intro'])) . '</p>';
		}
		
		if (!empty($processed['body_main'])) {
			$body .= '<p>' . nl2br(esc_html($processed['body_main'])) . '</p>';
		}
		
		if (!empty($processed['cta_url']) && !empty($processed['cta_text'])) {
			$body .= $this->render_button($processed['cta_url'], $processed['cta_text']);
		}
		
		return $body;
	}
	
	private function replace_placeholders($text, $placeholders) {
		foreach ($placeholders as $key => $value) {
			$text = str_replace('{' . $key . '}', $value, $text);
		}
		return $text;
	}
	
	private function wrap_html($content, $customer, $language = 'cs') {
		$logo_url = $this->get_logo_url($customer);
		$primary_color = $customer['primary_color'] ?? '#1e40af';
		
		ob_start();
		include $this->templates_dir . 'base.html.php';
		return ob_get_clean();
	}
	
	private function wrap_dual_html($cs_content, $en_content, $customer) {
		$logo_url = $this->get_logo_url($customer);
		$primary_color = $customer['primary_color'] ?? '#1e40af';
		
		ob_start();
		include $this->templates_dir . 'dual-wrapper.html.php';
		return ob_get_clean();
	}
	
	private function build_dual_text($cs_content, $en_content) {
		$text = "ČESKY\n";
		$text .= str_repeat('=', 40) . "\n\n";
		$text .= $cs_content['greeting'] . "\n\n";
		$text .= strip_tags($cs_content['body']) . "\n\n";
		$text .= $cs_content['footer'] . "\n\n";
		
		$text .= str_repeat('-', 50) . "\n\n";
		
		$text .= "ENGLISH\n";
		$text .= str_repeat('=', 40) . "\n\n";
		$text .= $en_content['greeting'] . "\n\n";
		$text .= strip_tags($en_content['body']) . "\n\n";
		$text .= $en_content['footer'] . "\n";
		
		return $text;
	}
	
	private function html_to_text($html) {
		$text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
		$text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
		$text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2: $1', $text);
		$text = preg_replace('/<h[1-6][^>]*>([^<]+)<\/h[1-6]>/i', "\n\n$1\n" . str_repeat('=', 40) . "\n", $text);
		$text = preg_replace('/<\/?(p|div|tr)[^>]*>/i', "\n", $text);
		$text = preg_replace('/<br\s*\/?>/i', "\n", $text);
		$text = strip_tags($text);
		$text = preg_replace('/\n{3,}/', "\n\n", $text);
		$text = trim($text);
		
		return $text;
	}
	
	private function get_logo_url($customer) {
		if (empty($customer['logo_url'])) {
			return '';
		}
		
		if (strpos($customer['logo_url'], 'http') === 0) {
			return $customer['logo_url'];
		}
		
		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/' . ltrim($customer['logo_url'], '/');
	}
	
	public function render_button($url, $text, $color = '#1e40af') {
		return '
		<table cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
			<tr>
				<td align="center" bgcolor="' . esc_attr($color) . '" style="border-radius: 6px;">
					<a href="' . esc_url($url) . '" target="_blank" 
					   style="display: inline-block; padding: 14px 28px; font-size: 16px; 
							  color: #ffffff; text-decoration: none; font-weight: bold;">
						' . esc_html($text) . '
					</a>
				</td>
			</tr>
		</table>';
	}
}