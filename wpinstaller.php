<?php
	function check_paths()
	{
		$distro_url=$_REQUEST['distro_url'];
		echo 'res_distro_url=>';
		//validate url
		$curl=curl_init($distro_url);
		curl_setopt($curl,CURLOPT_NOBODY,true);
		if (ini_get('open_basedir')==NULL)
			curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
		$ok=curl_exec($curl)&&(curl_getinfo($curl,CURLINFO_HTTP_CODE)==200);
		curl_close($curl);
		echo $ok?'':'<span class="conf_bad">Не смогу скачать</span>';
		echo '|res_install_dir=>';
		//validate install_dir
		$install_dir_rel=trim($_REQUEST['install_dir'],'/');
		if ($install_dir_rel!='')	$install_dir_rel='/'.$install_dir_rel;
		$install_dir = $_SERVER['DOCUMENT_ROOT'].$install_dir_rel;
		if (!file_exists($install_dir)) {
			$install_dir=substr($install_dir,0,strrpos($install_dir,'/'));
			$ok=(is_dir($install_dir)&&is_writable($install_dir));
		} else {
			$ok=(is_dir($install_dir)&&is_writable($install_dir));
			if ($ok)
			{
				//проверяем, что каталог пустой
				if ($handle = opendir($install_dir)) {
					while (false !== ($file = readdir($handle))) {
    					if ($file != "." && $file != ".." && $install_dir_rel.'/'.$file != $_SERVER['PHP_SELF'] ) {
        					echo '<span class="conf_bad">Каталог не пустой</span>';die;
    					}
					}
					closedir($handle);
				} else
					$ok=false;
			}
		}
		echo $ok?
			'':
			'<span class="conf_bad">Не смогу создать</span>';
		echo '|final_distro_url=>'.$distro_url.'|final_install_dir=>http://'.$_SERVER['SERVER_NAME'].$install_dir_rel;
	}
	
	function check_db()
	{
		$db_host=$_REQUEST['db_host'];
		$db_user=$_REQUEST['db_user'];
		$db_password=$_REQUEST['db_password'];
		$db_name=$_REQUEST['db_name'];
		$db_table_prefix=$_REQUEST['db_table_prefix'];
		echo 'res_db_host=>';
		$link=@mysql_connect($db_host,$db_user,$db_password);
		if ($link)
		{
			$client=mysql_get_client_info();
			if ($client<'4.0')
			{
				echo '<span class="conf_bad">Нужен 4.0 (есть '.$client.')</span>|res_db_user=>|res_db_password=>|res_db_name=>|res_db_table_prefix=>';
				die;
			}
			echo '|res_db_user=>|res_db_password=>|res_db_name=>';
			if (!mysql_select_db($db_name,$link))
				echo '<span class="conf_bad">Нет такой!</span>|res_db_table_prefix=>';
			else {
				echo '|res_db_table_prefix=>';
				$check= mysql_query('SELECT 1 FROM `'.$db_table_prefix.'options` WHERE 0',$link);
				if ($check)
					echo '<span class="conf_bad">Уже используется</span>';
			}
		} else {
			$errno=mysql_errno();
			switch ($errno)
			{
			case 1045:
			 	echo '|res_db_user=><span class="conf_bad">Ошибка</span>|res_db_password=><span class="conf_bad">авторизации</span>|';
			 	break;
			default:
			 	echo '<span class="conf_bad">Не могу соединиться!</span>|res_db_user=>|res_db_password=>|';
			 	break;
			}
			echo 'res_db_name=>|res_db_table_prefix=>';
		}
		echo '|final_db=>'.$db_name.' на '.$db_user.'@'.$db_host.'|final_db_table_prefix=>'.$db_table_prefix;
	}
	
	if (array_key_exists('check_paths',$_REQUEST)) {
		check_paths();
		die;
 	} elseif (array_key_exists('check_db',$_REQUEST)) {
		check_db();
		die;
 	}


//Настройки по умолчанию
	$distro_url='http://wordpress.org/latest';
	$db_host='';
	$db_table_prefix='wp_';
	$install_dir='/';
// Проверяем возможность установки
	$safe_mode=ini_get('safe_mode');
	
	$ok_php=PHP_VERSION>='4.2';
	$ok_safemode=!$safe_mode;
	$temp_dir=sys_get_temp_dir();
	$ok_writable=true;
	if (!is_writable($temp_dir))
		$temp_dir=getcwd();
	if (!is_writable($temp_dir))
		$ok_writable=false;
	
	$ok=$ok_php && $ok_safemode && $ok_writable;
	
//////////////////////////////// УСТАНОВКА
	if (array_key_exists('submit',$_REQUEST))
	{
		$distro = $_REQUEST['distro_url'];
		//нам нужен tar.gz
		if (strtolower(substr($distro,strrpos($distro,'.')))=='.zip')
		{ 
			$distro=substr($distro,0,strrpos($distro,'.')).'.tar.gz';
		}
		
		//загружаем архив
		$curl=curl_init($distro);		
		$fname=$temp_dir.'/'.uniqid().'.tar.gz';
		$f=fopen($fname,'w');
		curl_setopt($curl,CURLOPT_FILE,$f);
		if (ini_get('open_basedir')==NULL)
			curl_setopt($curl,CURLOPT_FOLLOWLOCATION,true);
		curl_exec($curl);
		curl_close($curl);
		fclose($f);

		$install_dir_rel=trim($_REQUEST['install_dir'],'/');
		if ($install_dir_rel!='')	$install_dir_rel='/'.$install_dir_rel;
		$install_dir = $_SERVER['DOCUMENT_ROOT'].$install_dir_rel;
		
		//создаем папку для WordPress
		if (!file_exists($install_dir))
			mkdir($install_dir);
		chdir($install_dir);
		
		//распаковываем архив
		system('tar zxvf '.$fname);
		system('mv '.$install_dir.'/wordpress/* '.$install_dir);
		unlink($fname);
		rmdir('wordpress');
		
		//создаем конфиг
		$db_name=$_REQUEST['db_name'];
		$db_user=$_REQUEST['db_user'];
		$db_password=$_REQUEST['db_password'];
		$db_host=$_REQUEST['db_host'];
		$db_table_prefix=$_REQUEST['db_table_prefix'];
		
		$f=fopen($install_dir.'/wp-config.php','w');
		fputs($f,<<<EOT
<?php
// ** MySQL настройки ** //
define('DB_NAME', '$db_name');
define('DB_USER', '$db_user');
define('DB_PASSWORD', '$db_password');
define('DB_HOST', '$db_host');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// вы можете иметь несколько инсталляций wordpress в одной базе данных, но для каждой задайте отдельный префикс
\$table_prefix  = '$db_table_prefix';   // только цифры. буквы и символ подчеркивания!

define ('WPLANG', 'ru_RU');

/* Это все, дальше не редактирвутйе! Удачного блоггинга. */

define('ABSPATH', dirname(__FILE__).'/');
require_once(ABSPATH.'wp-settings.php');
?>
EOT
			);
		fclose($f);
		
		//<?обновляем права
		switch($_REQUEST['perms'])
		{
			case 1:
				system('chmod 0777 '.$install_dir);
				system('chmod -R 0777 '.$install_dir.'/wp-content/themes');
				system('chmod -R 0777 '.$install_dir.'/wp-content/plugins');
				break;
			case 2:
				system('chmod -R 0777 '.$install_dir);
				break;
		}
		
		header('Location: '.$install_dir_rel.'/wp-admin/install.php');
		die;
	}
//////////////////////////////// УСТАНОВКА
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<meta name="Generator" content="Microsoft Word 97">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<title>WordPress Remote Installer by coldFlame</title>
		<style>
			* {padding:0;margin:0}
			html {background:#9ac80d}
			body { font-family: "Georgia",serif; width:750px;margin:0 auto}
			div#content {font-family: "Arial",sans-serif;  background:white; padding:1em}
			div#header {background:#465d02;color:#9ac80d;padding:2em}
			div#header a {color:white;text-decoration:none}
			div#header h1 {color:white;font-weight:bold;font-size:2.5em}
			div#header h1 sup {font-size:.5em}
			div#header h2 {font-size:1.2em}
			div#footer {font-family: "Arial",sans-serif; background:black;color:white;text-align:center;font-size:.8em;padding:.5em}
			div#footer a {color:white}
			div#content h2 {color:#9ac80d;padding:.5em;font-size:1.5em}
			div#nonFooter {border: solid 2px #00722d}
			div#content p {font-size:1em;padding:.5em}

			div#bigwarning{text-align:center;color:red;font-weight:bold;padding:.1em;font-size:1.2em}
			span.small {font-size:.7em}

			ul {list-style-type:none}
			span.conf_title {float:left;width:30em}			
			span.final_title {float:left;width:15em}			
			span.conf_ok {color:#2d9500;font-weight:bold}
			span.conf_bad {color:#e51837;font-weight:bold}
			div.error { font-size:.8em;color:red}
			/*************************************** FORMS */
			fieldset {  
			    border-style:none;
			}
			
			legend {  
			    margin-left: 1em;  
			    color: #000000;  
			    font-weight: bold;
			}
			fieldset ul {  
			    padding: .5em;
			    list-style: none;
			    text-align:left;
			}
			fieldset li {  
			    padding-bottom: .2em;
			}
			
			fieldset.text_editor {
			    border-style:none;
			}
			
			input.submit {
				font-size:1em;
				font-family:"Arial",sans-serif;
			    width:25em;
			    margin:.5em;
			    margin-left:10em;
			}
			
			input.button {
				width:12em;
				margin:.5em;
				font-size:1em;
				font-family:"Arial",sans-serif;
			}
			
			fieldset label {
				float:left;
			    display:block;
				width:15em;			    
			}
			
			fieldset input.text {
			    width:15em;
			    border:solid 1px #cccccc;
			    font-family:"Arial",sans-serif;
			    font-size:1em
			}

			fieldset label.radio {
			    display:inline;
			}

			/*************************************** FOOTER STICK */
			html
			{
			    height: 100%;
			}
			
			body
			{
			    height: 100%;
			}
			
			#nonFooter
			{
			    position: relative;
			    min-height: 100%;
			    background-color:white;
			}
			
			* html #nonFooter
			{
			    height: 100%;
			}
			
			#content
			{
			    padding-bottom: 5em;
			}
			
			#footer
			{
			    position: relative;
			    margin-top: -2em;
			} 
			</style>
		<script>
			//Created by Sean Kane (http://celtickane.com/programming/code/ajax.php)
			//Feather Ajax v1.0.1
		
			function AjaxObject101() {
				this.createRequestObject = function() {
					try {
						var ro = new XMLHttpRequest();
					}
					catch (e) {
						var ro = new ActiveXObject("Microsoft.XMLHTTP");
					}
					return ro;
				}
				this.sndReq = function(action, url, data) {
					if (action.toUpperCase() == "POST") {
						this.http.open(action,url,true);
						this.http.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
						this.http.onreadystatechange = this.handleResponse;
						this.http.send(data);
					}
					else {
						this.http.open(action,url + '?' + data,true);
						this.http.onreadystatechange = this.handleResponse;
						this.http.send(null);
					}
				}
				this.handleResponse = function() {
					if ( me.http.readyState == 4) {
						var rawdata = me.http.responseText.split("|");
						for ( var i = 0; i < rawdata.length; i++ ) {
							var item = (rawdata[i]).split("=>");
							if (item[0] != "") {
								if (item[1].substr(0,3) == "%V%" ) {
									document.getElementById(item[0]).value = item[1].substring(3);
								}
								else  {
									document.getElementById(item[0]).innerHTML = item[1];
								}
							}
						}
						if (typeof me.funcDone == 'function') { me.funcDone();}
					}
					if ((me.http.readyState == 1) && (typeof me.funcWait == 'function')) { me.funcWait(); }
				}
				var me = this;
				this.http = this.createRequestObject();
				
				var funcWait = null;
				var funcDone = null;
			}
			
			var ao = new AjaxObject101(); 
			var lastvalues = new Array();
					    
		    function check_paths()
		    {
				document.getElementById('submit_paths').value='Ждем...';
				document.getElementById('submit_paths').disabled=true;
				distro_url=document.getElementById('i_distro_url').value;
				install_dir=document.getElementById('i_install_dir').value;
				ao.funcDone=check_paths_done;
		        ao.sndReq('get','wpinstaller.php','check_paths&distro_url='+encodeURIComponent(distro_url)+'&install_dir='+encodeURIComponent(install_dir));
			}
			
			function check_paths_done()
			{
				document.getElementById('submit_paths').disabled=false;
				document.getElementById('submit_paths').value='Дальше';
				if (
					(document.getElementById('res_distro_url').innerHTML=='')&&
					(document.getElementById('res_install_dir').innerHTML=='')
					)
				{
					go('paths','db');
				}
			}

		    function check_db()
		    {
				document.getElementById('submit_db').value='Ждем...';
				document.getElementById('submit_db').disabled=true;
				db_host=document.getElementById('i_db_host').value;
				db_user=document.getElementById('i_db_user').value;
				db_password=document.getElementById('i_db_password').value;
				db_name=document.getElementById('i_db_name').value;
				db_table_prefix=document.getElementById('i_db_table_prefix').value;
				ao.funcDone=check_db_done;
		        ao.sndReq('get','wpinstaller.php','check_db'+
					'&db_host='+encodeURIComponent(db_host)+
					'&db_user='+encodeURIComponent(db_user)+
					'&db_password='+encodeURIComponent(db_password)+
					'&db_name='+encodeURIComponent(db_name)+
					'&db_table_prefix='+encodeURIComponent(db_table_prefix)
					);
			}

			function check_db_done()
			{
				document.getElementById('submit_db').disabled=false;
				document.getElementById('submit_db').value='Дальше';
				if (
					(document.getElementById('res_db_host').innerHTML=='')&&
					(document.getElementById('res_db_user').innerHTML=='')&&
					(document.getElementById('res_db_password').innerHTML=='')&&
					(document.getElementById('res_db_name').innerHTML=='')&&
					(document.getElementById('res_db_table_prefix').innerHTML=='')
					)
				{
					go('db','perms');
				}
			}

			function check_perms()
			{
				if (document.getElementById('perms_0').checked)
					document.getElementById('final_perms').innerHTML='Как есть';
				else if (document.getElementById('perms_1').checked)
					document.getElementById('final_perms').innerHTML='Рекомендованные';
				else if (document.getElementById('perms_2').checked)
					document.getElementById('final_perms').innerHTML='Открыть на все';
				go('perms','final');
			}

			
			function go(from,to)
			{
					document.getElementById(from).style.display='none';
					document.getElementById(to).style.display='block';
			}
		</script>
	</head>
	<body>
		<div id="nonFooter">
		<div id="header">
		<h1><a href="http://coldflame.in.ua/wpinstaller">WordPress Remote Installer <sup>1.0 &beta;eta</sup></a></h1>
		<h2>Установка WordPress <em>действительно</em> займет пять минут!</h2>
		</div>
		<div id="content">
		<div id="bigwarning">ВАЖНО! Этот скрипт представляет угрозу безопасности сайта!<br>Удали его сразу после установки!</div>
		<div id="pre">
		<p>Чтобы стала возможной знаменитая "пятиминутная установка WordPress", нужно для начала развернуть дистрибутив на сервере, править ручками конфиг, да еще и ошибок не наделать... Будем откровенны друг с другом - разрабы снова навешали бедным пользователям лапши на уши.</p>
		<p>Забудь все это! WordPress Remote Installer скачает и распакует WordPress и даже подготовит конфиг - никакой ручной работы. Создать блог на ЖЖ сложнее, наверное. Давно не пробовал. :)</p>
		<h2>Перед установкой</h2>
		<p>Не забудь, что тебе все-таки придется создать базу данных и пользователя к ней. Как это сделать - спроси у службы поддержки твоего хостинга.</p>
		<h2>Проверяем хостинг...</h2>
		<ul style="padding:.5em">
		<li><span class="conf_title">Веб-хостинг с поддержкой PHP</span> <span class="conf_ok">Имеется!</span>
		</li>
		<li><span class="conf_title">Версия PHP</span> <span class="<?php echo $ok_php?'conf_ok':'conf_bad'?>"><?php echo PHP_VERSION?></span>
<?php if (!$ok_php) { ?>
		<div class="error">Версия PHP меньше 4.2! К сожалению, WordPress не может работать.</div>
<?php } ?>
		</li>
		<li><span class="conf_title">safe_mode</span> <span class="<?php echo $ok_safemode?'conf_ok':'conf_bad'?>"><?php echo $safe_mode?'On':'Off'?></span>
<? if (!$ok_safemode) { ?>
		<div class="error">В настройках PHP включен <a href="http://ua.php.net/manual/ru/features.safe-mode.php">Безопасный режим</a>.<br>Этот установщик требует отключенного safe_mode. К тому же, в безопасном режиме WordPress не сможет загружать файлы.</div>
<? } ?>
		</li>
		<li><span class="conf_title">Права на запись</span> <span class="<?php echo $ok_writable?'conf_ok':'conf_bad'?>"><?php echo $ok_writable?'Есть':'Нет'?></span>
<? if (!$ok_writable) { ?>
		<div class="error">У скрипта нет прав на запись в каталог временных файлов. Это значит, что ему некуда будет скачать WordPress.</div>
<? } ?>
		</li>
		</ul>
<? if ($ok) { ?>
			<input type="button" class="submit" value="Замечательно! Можно устанавливать" 
				onClick="document.getElementById('pre').style.display='none';document.getElementById('process').style.display='block';" default><br>
<? } ?>
		</div>
<? if ($ok) { ?>
		<div id="process" style="display:none">
		<h2>Замечательно! Можно устанавливать</h2>
		<form method="post" action="">
			<div id="paths">
			<fieldset>
			<legend>Что куда</legend>
			<ul>
				<li>
					<label for="i_distro_url">URL дистрибутива (в tar.gz!)</label> 
					<input 
						name="distro_url" 
						class="text" 
						type="text" 
						id="i_distro_url"
						value="<?php echo $distro_url?>">
					<span id="res_distro_url">*</span>
				<li>
					<label for="i_install_dir">Каталог для установки</label> 
						<input 
							name="install_dir" 
							class="text" 
							type="text" 
							id="i_install_dir" 
							value="<?php echo $install_dir?>">
					<span id="res_install_dir">*</span>
			</ul>
			</fieldset>
			<fieldset class="submit">
				<input class="button" style="margin-left:10em" type="button" value="Назад" onClick="go('process','pre')">
				<input class="button" type="button" value="Дальше" onClick="check_paths();" id="submit_paths" default>
			</fieldset>
			</div>
			<div id="db" style="display:none">
			<fieldset>
			<legend>База данных</legend>
			<ul>
				<li>
					<label for="i_db_host">Адрес сервера MySQL</label> 
					<input name="db_host" 
						class="text" 
						type="text" 
						id="i_db_host">
					<span id="res_db_host">*</span>
				<li>
					<label for="i_db_user">Пользователь</label> 
					<input name="db_user" class="text" type="text" id="i_db_user">
					<span id="res_db_user">*</span>
				<li>
					<label for="i_db_password">Пароль</label> 
					<input 
						name="db_password" 
						type="password" 
						class="text" 
						id="i_db_password">
					<span id="res_db_password">*</span>
				<li>
					<label for="i_db_host">База данных</label> 
					<input 
						name="db_name" 
						class="text" 
						type="text" 
						id="i_db_name">
					<span id="res_db_name">*</span>
				<li>
					<label for="i_db_table_prefix">Префикс таблиц</label> 
					<input 
						name="db_table_prefix"
						class="text" 
						type="text" 
						id="i_db_table_prefix"
						value="<?php echo $db_table_prefix?>" 
						>
					<span id="res_db_table_prefix">*</span>
			</ul>
			</fieldset>
			<fieldset class="submit">
				<input class="button" style="margin-left:10em" type="button" value="Назад" onClick="go('db','paths')">
				<input class="button" type="button" value="Дальше" onClick="check_db()" id="submit_db" default>
			</fieldset>
			</div>
			<div id="perms" style="display:none">
			<fieldset>
				<legend>Права на доступ к файлам</legend>
				<ul>
					<li><input type="radio" name="perms" value="0" id="perms_0"> Оставить права как есть.</li>
					<li><input type="radio" name="perms" value="1" checked id="perms_1"> Использовать права, <a href="http://codex.wordpress.org/Changing_File_Permissions">рекомендованные WordPress</a>.</li>
					<li><input type="radio" name="perms" value="2" id="perms_2"> Открыть права записи на все файлы WordPress (0777).</li>
				</ul>
			</fieldset>
			<fieldset class="submit">
				<input class="button" style="margin-left:10em" type="button" value="Назад" onClick="go('perms','db')">
				<input class="button" type="button" value="Дальше" onClick="check_perms()" default>
			</fieldset>
			</div>
			<div id="final" style="display:none">
			  <fieldset class="submit">
				<legend>Финал</legend>
				<ul>
					<li>
						<span class="final_title">Дистрибутив</span>
						<span class="conf_ok" id="final_distro_url"></span><br>
					</li>
					<li>
						<span class="final_title">Адрес будущего блога</span>
						<span class="conf_ok" id="final_install_dir"></span><br>
					</li>
					<li>
						<span class="final_title">База данных</span>
						<span class="conf_ok" id="final_db"></span><br>
					</li>
					<li>
						<span class="final_title">Префикс таблиц базы данных</span>
						<span class="conf_ok" id="final_db_table_prefix"></span><br>
					</li>
					<li>
						<span class="final_title">Права</span>
						<span class="conf_ok" id="final_perms"></span><br>
					</li>
				</ul>
				<input class="button" style="margin-left:10em" type="button" value="Назад" onClick="go('final','perms')">
				<input class="button" type="submit" name="submit" value="Поехали!!!" default>
			  </fieldset>
			</div>
		</form>
		</div>
<?php }  else { ?>
		<div class="error">К сожалению, при установке обнаружились проблемы. Пожалуйста, устрани их и попробуй еще раз.</div>
<?php } ?>
		</div>
		</div>
		<div id="footer">&copy; 2008 <a href="http://coldflame.in.ua/">coldFlame.in.ua</a><br>
		Спасибо <a href="http://wordpress.org">WordPress</a>, спасибо команде <a href="http://mywordpress.ru">MyWordPress.ru</a>, спасибо Sean Kane за <a href="http://celtickane.com/programming/code/ajax.php">FeatherAJAX</a></div>
	</body>
</html>
