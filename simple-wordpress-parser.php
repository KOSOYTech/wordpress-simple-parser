<?php
/*
Plugin Name: Simple WordPress Parser
Plugin URI: https://kosoy-tech.ru/produkty
Description: Plugin parses needed content from needed URL of any site by CSS selector and shows it by [parsedwp] shortcode. Plugin use PHPQuery.
Version: 1.0
Author: KOSOY
Author URI: https://kosoy-tech.ru
License: GPL2
*/

/*  Copyright YEAR  PLUGIN_AUTHOR_NAME  (email : maxim@kosoy-tech.ru)
 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Вешаем хук на событие до загрузки админ-панели WordPress. Далее к этому хуку мы подключим указанную здесь функцию.
add_action('admin_menu', 'add_plugin_page');

// Функция создания страницы настроек плагина, которая будет повешена на хук, объявленный ранее
function add_plugin_page() {

// Специальной WP функцией добавляем подраздел настроек в раздел "Параметры" админ-панели WP. И передаем этой функции по очереди заголовок страницы настроек, заголовок для меню, уровень допуска для пользователей админ-меню, SLUG меню настроек плагина (либо ссылку на файл, если страница настроек будем разработана в отдельном файле) и функцию, которая непосредствено создает страницу настроек
	add_options_page( 'Simple WordPress Parser', 'Simple WP Parser', 'manage_options', 'simple_wp_parser', 'parser_options_page_output' );
  
}

// Функция, создающая интерфейс страницы настроек
function parser_options_page_output(){
  
// Выписываем название текущей страницы админ-панели в заглавие (название плагина) и подключаем форму для заполнения настроек плагина
	?>
	<div class="wrap">
		<h2><?php echo get_admin_page_title() ?></h2>
		<form action="options.php" method="POST">
			<?php
      
// Выводим скрытые поля для защиты нашей формы и указываем название переменной-идентификатора этих скрытых настроек. Далее мы зарегистрируем и создадим эту переменную-идентификатор.
				settings_fields( 'parser_hidden_option' );

// Выводим секцию с полями наших настроек, указывая название переменной-идентификатора наших настроек. Далее мы зарегистрируем и создадим эту переменную-идентификатор.
				do_settings_sections( 'parser_section_options' );
 
// Выводим кнопку сохранения изменений
				submit_button();
			?>
		</form>
	</div>
	<?php
}

// Вешаем функцию создания настроек плагина на хук, который срабатывает после полной загрузки страницы администратора. Далее мы создадим эту функцию
add_action('admin_init', 'plugin_settings');

// Функция создания и регистрации настроек нашего плагина
function plugin_settings(){
  
// Регистрируем скрытые настройки и придумываем название для всех наших настроек, которое будет храниться в БД, а также указываем функцию, которая будет очищать вводимые данные
	register_setting( 'parser_hidden_option', 'parser_db_option_name', 'sanitize_callback' );

// Регистрируем страницу настроек, передавая ID, к которому будем цеплять каждую настройку, заголовок секции, функцию вывода описания, если необходимо и переменную-идентификатор страницц, на которой настройки будут выводиться
	add_settings_section( 'parser_section_id', 'Settings', '', 'parser_section_options' ); 

// Регистрируем первую настройку, передавая ей поочередно название переменной, текстовое описание, функцию, которая возвращает поля ввода, переменную-идентификатор страницы, переменную-идентификатор секции самих настроек. (Ввод URL адреса документа, откуда необходимо спарсить контент)
	add_settings_field('doc_url_field', 'URL of the page, that should be parsed', 'doc_url_input_function', 'parser_section_options', 'parser_section_id' );

// Регистрируем вторую настройку по тому же принципу, что и выше. (CSS селектор элемента, который необходимо спарсить с указанной страницы)
	add_settings_field('selector_field', 'CSS Selector of the element(s) you want to parse', 'selector_input_function', 'parser_section_options', 'parser_section_id' );
}

// Функция вывода полей для 1-ой настройки. (Ввод URL адреса документа, откуда необходимо спарсить контент)
function doc_url_input_function(){
	$val = get_option('parser_db_option_name');
	$val = $val ? $val['parsurl'] : null;
	?>
	<input type="text" name="parser_db_option_name[parsurl]" value="<?php echo esc_attr( $val ) ?>" />
	<?php
}

// Функция вывода полей для 1-ой настройки. (CSS селектор элемента, который необходимо спарсить с указанной страницы)
function selector_input_function(){
	$val = get_option('parser_db_option_name');
	$val = $val ? $val['parsclass'] : null;
	?>
	<input type="text" name="parser_db_option_name[parsclass]" value="<?php echo esc_attr( $val ) ?>" />
	<?php
}

## Очистка данных
function sanitize_callback( $options ){ 
	// очищаем
	foreach( $options as $name => & $val ){
		if( $name == 'input' )
			$val = strip_tags( $val );
	}

	//die(print_r( $options )); // Array ( [input] => aaaa [checkbox] => 1 )

	return $options;
}

## Функция парсинга нужного фрагмента

function parse_process() {

// Подключаем библиотеку PHPQuery при помощи функции WordPress, которая определяет текущую директорию плагина, из которой она была вызвана
include(plugin_dir_path( __FILE__ ) . 'phpQuery.php');
	
$val = get_option('parser_db_option_name');
$valurl = $val ? $val['parsurl'] : null;
$selector = $val ? $val['parsclass'] : null;

$url = $valurl;
  
## Блок получения HTML-кода целевой страницы при помощи CURL
  
// Создаем переменную CURL, в которую загружаем URL адрес целевой страницы и в которую мы загрузим настройки CURL-сессии
$curl = curl_init($url);
  
// CURL-настройка, указывающая, что необходимо сохранить ответ в переменную, а не выводить его на экран
curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

// CURL-настройка для перехода по редиректу, в случае его наличия
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
  
// Запускаем CURL сесссию и сохраняем ответ в переменную
$page = curl_exec($curl);

// Вытаскиваем информацию о кодировке из заголовка Content Type ответа исходной страницы
$ctype = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
  
// Проверяем при помощи регулярных выражений, не пришел ли пустой заголовок и указана ли кодировка в заголовке, либо указана ли кодировка в исходном коде странице
if (($ctype !== null && preg_match('%charset=([\w-]+)%i', $ctype, $matches))
    || preg_match('%<meta[^>]+charset=[\'"]?([\w-]+)%i', $page, $matches)) {
  
// Записываем полученную кодировку в переменную (первое найденное регулярными выражениями значение)
    $charset = $matches[1];
}

// Если исходная кодировка документа не равна UTF-8, то мы меняем ее на UTF-8
if ($charset && strtoupper($charset) !== 'UTF-8') {
    $page = iconv($charset, 'UTF-8', $page);
}
  
## Конец блока получения HTML-кода целевой страницы при помощи CURL

// Сохраняем загруженную HTML-страницу с помощью phpQuery в переменную для дальнейшего разбора
$document = phpQuery::newDocument($page);
  
// Проверяем, удалось ли загрузить страницу в переменную
if (!isset($document)) {return "Извините, не удалось загрузить указанную страницу";}
  
// Ищем в спарсенной странице фрагмент с выбранным селектором и сохраняем его
$fragment = $document->find($selector);
  
// Если в переменной есть спарсенный фрагмент - возвращаем его
if (isset($fragment)) {return $fragment;} else {return "Извините, произошла неизвестная ошибка";}
}

## Конец функции парсинга нужного фрагмента

// Вешаем шорткод parsedwp на функцию parse_process для вывода спарсенного фрагмента
add_shortcode('parsedwp', 'parse_process');

?>