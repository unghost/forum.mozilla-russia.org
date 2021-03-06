<?php

// Determine what locale to use
switch (PHP_OS)
{
	case 'WINNT':
	case 'WIN32':
		$locale = 'russian';
		break;

	case 'FreeBSD':
	case 'NetBSD':
	case 'OpenBSD':
		$locale = 'ru_RU.UTF-8';
		break;

	default:
		$locale = 'ru_RU.UTF-8';
		break;
}

// Attempt to set the locale
setlocale(LC_CTYPE, $locale);
// hcs merge posts update
setlocale(LC_TIME, $locale);

$lang_common = [

// Text orientation and encoding
'lang_direction'		=>	'ltr', // ltr (Left-To-Right) or rtl (Right-To-Left)
'lang_encoding'			=>	'utf-8',
'lang_multibyte'		=>	true,
'db_lang_encoding'		=>	'utf8',

// Notices
'Bad request'			=>	'Неверный запрос. Ссылка, по которой вы пришли неверная или устаревшая.',
'No view'				=>	'Вы не имеете прав для просмотра этих форумов.',
'No permission'			=>	'Вы не имеете прав для доступа к этой странице.',
'Bad referrer'			=>	'Неверный источник (HTTP_REFERER). Вы попали на эту страницу из несанкционированного источника. Пожалуйста, вернитесь и попробуйте ещё раз. Если проблема осталась, пожалуйста, убедитесь что \'Начальный URL\' правильно установлен в Администрирование/Свойства и, что, Вы попадаете на форум через этот URL.',

// Topic/forum indicators
'New icon'				=>	'Есть новые сообщения',
'Normal icon'			=>	'<!-- -->',
'Closed icon'			=>	'Эта тема закрыта',
'Redirect icon'			=>	'Форум перенесён',

// Miscellaneous
'Announcement'			=>	'Объявление',
'Announcements'			=>	'Объявления',
'Helpful info'			=>	'Полезная информация',
'Options'				=>	'Параметры',
'Actions'				=>	'Действия',
'Submit'				=>	'Отправить', // "name" of submit buttons
'Preview'				=>	'Предварительный просмотр', // submit button to preview message
'Ban message'			=>	'На этом форуме Вы находитесь в чёрном списке (забанены).',
'Ban message 2'			=>	'Действие Вашего бана истекает',
'Ban message 3'			=>	'Забанивший Вас администратор или модератор оставил следующее сообщение:',
'Ban message 4'			=>	'Если у вас есть какие-либо вопросы, вы можете обратиться к администратору',
'Never'					=>	'Никогда',
'Today'					=>	'Сегодня',
'Yesterday'				=>	'Вчера',
'Info'					=>	'Информация', // a common table header
'Go back'				=>	'Вернуться назад',
'Maintenance'			=>	'Сервис',
'Redirecting'			=>	'Переадресация',
'Click redirect'		=>	'Нажмите сюда, если вы не хотите больше ждать (или если браузер не перенаправляет Вас автоматически)',
'on'					=>	'включены', // as in "BBCode is on"
'off'					=>	'отключены',
'Invalid e-mail'		=>	'Введённый вами e-mail - неправильный',
'required field'		=>	'это поле обязательно для заполнения в этой форме.', // for javascript form validation
'Last post'				=>	'Последнее сообщение',
'by'					=>	'-', // as in last post by someuser
'New posts'				=>	'Новые&nbsp;сообщения', // the link that leads to the first new post (use &nbsp; for spaces)
'New posts info'		=>	'Перейти к первому новому сообщению в этой теме.', // the popup text for new posts links
'Username'				=>	'Имя',
'Password'				=>	'Пароль',
'E-mail'				=>	'E-mail',
'Send e-mail'			=>	'Послать e-mail',
'Moderated by'			=>	'Модераторы:',
'Registered'			=>	'Зарегистрирован',
'Subject'				=>	'Заголовок',
'Message'				=>	'Сообщений',
'Topic'					=>	'Тема',
'Forum'					=>	'Форум',
'Posts'					=>	'Сообщений',
'Replies'				=>	'Ответов',
'Author'				=>	'Автор',
'Pages'					=>	'Страницы',
'Pages manual'			=>	'Перейти на страницу',
'BBCode'				=>	'BB-коды', // You probably shouldn't change this
'img tag'				=>	'Картинки',
'Smilies'				=>	'Смайлики',
'and'					=>	'и',
'Image link'			=>	'изображение', // This is displayed (i.e. <image>) instead of images when "Show images" is disabled in the profile
'wrote'					=>	'пишет', // For [quote]'s
'Code'					=>	'Код', // For [code]'s
'Code select'			=>	'Выделить код',
'Mailer'				=>	'Почтовый робот', // As in "MyForums Mailer" in the signature of outgoing e-mails
'Important information'	=>	'Важная информация',
'Write message legend'	=>	'Напишите ваше сообщение и нажмите отправить (Ctrl+Enter)',
'Google search'			=>	'Поиск в Google',
'Google loading'		=>	'Загрузка поиска Google...',
'Search topic'			=>	'Поиск по теме',
'Search forum'			=>	'Поиск по разделу',
'Search forums'			=>	'Поиск по форуму',
'Forum language'		=>	'Язык отображения форума',

// Title
'Title'					=>	'Статус',
'Member'				=>	'Участник', // Default title
'Moderator'				=>	'Модератор',
'Administrator'			=>	'Администратор',
'Banned'				=>	'Забанен',
'Guest'					=>	'Гость',

// Stuff for include/parser.php
'BBCode error'			=>	'Синтаксис тегов BBCode в сообщении неверен.',
'BBCode error 1'		=>	'Отсутствует начальный тег для [/quote].',
'BBCode error 2'		=>	'Отсутствует конечный тег для [code].',
'BBCode error 3'		=>	'Отсутствует начальный тег для [/code].',
'BBCode error 4'		=>	'Отсутствует один или более конечных тегов для [quote].',
'BBCode error 5'		=>	'Отсутствует один или более начальных тегов для [/quote].',
'BBCode error 6'		=>	'Отсутствует конечный тег для [spoiler].',
'BBCode error 7'		=>	'Отсутствует начальный тег для [/spoiler].',
'BBCode error 8'		=>	'Отсутствует конечный тег для [noindex].',
'BBCode error 9'		=>	'Отсутствует начальный тег для [/noindex].',
'BBCode error no opening tag' => 'Обнаружен парный тег [/%1$s] без соответствующего начального тега [%1$s]',
'BBCode error invalid nesting' => 'Тег [%1$s] открывается внутри [%2$s], это недопустимо',
'BBCode error invalid self-nesting' => 'Тег [%s] открывается внутри такого же тега, это недопустимо',
'BBCode error no closing tag' => 'Обнаружен парный тег [%1$s] без соответствующего закрывающего тега [/%1$s]',
'BBCode error empty attribute' => 'Тег [%s] с пустым атрибутом',
'BBCode error tag not allowed'		=>	'Вам нельзя использовать тег [%s]',
'BBCode list size error' => 'Ваш список слишком велик, пожалуйста уменьшите его!',

// Stuff for the navigator (top of every page)
'Index'					=>	'Форумы',
'User list'				=>	'Пользователи',
'Rules'					=>	'Правила',
'Search'				=>	'Поиск',
'Register'				=>	'Регистрация',
'Login'					=>	'Зайти',
'Not logged in'			=>	'Вы не зашли.',
'Profile'				=>	'Профиль',
'Logout'				=>	'Выйти',
'Logged in as'			=>	'Вы зашли как',
'Admin'					=>	'Администрирование',
'Last visit'			=>	'Ваш последний визит',
'Show new posts'		=>	'Показать новые сообщения с момента последнего визита',
'Mark all as read'		=>	'Пометить все форумы как прочитанные',
'Link separator'		=>	'', // The text that separates links in the navigator
'Mark forum as read'	=>	'Пометить форум как прочитанный', // MOD: MARK TOPICS AS READ
'New reports'			=>	'Есть новые жалобы',

// Stuff for the page footer
'Board footer'			=>	'Board footer',
'Search links'			=>	'Search links',
'Show recent posts'		=>	'Показать новые сообщения за последние 24 часа',
'Show unanswered posts'	=>	'Показать сообщения, не имеющие ответов',
'Show your posts'		=>	'Показать Ваши сообщения',
'Show subscriptions'	=>	'Показать темы, на которые Вы подписаны',
'Show active posts'		=>	'Показать наиболее обсуждаемые темы',
'Jump to'				=>	'Перейти',
'Go'					=>	' Перейти ', // submit button in forum jump
'Move topic'			=>	'Перенести тему',
'Open topic'			=>	'Открыть тему',
'Close topic'			=>	'Закрыть тему',
'Unstick topic'			=>	'Открепить тему',
'Stick topic'			=>	'Прикрепить тему',
'Unstick post'			=>	'Открепить первое сообщение',
'Stick post'			=>	'Прикрепить первое сообщение',
'Poll to topic'			=>	'Преобразовать опрос в тему',
'Moderate forum'		=>	'Модерировать форум',
'Delete posts'			=>	'Модерировать сообщения',
'Debug table'			=>	'Отладочная информация',

// For extern.php RSS feed
'RSS Desc Active'		=>	'Наиболее активные обсуждения на', // board_title will be appended to this string
'RSS Desc New'			=>	'Новые темы на', // board_title will be appended to this string
'RSS Desc Messages'		=>	'Новые сообщения на',
'RSS Site News'			=>	'Новости на',
'Posted'				=>	'Размещено', // The date/time a topic was started
'Comments'				=>	'Комментарии',
// MOD Printable topic version string
'Print version'			=>	'Версия для печати',

'Show More'				=>	'Больше »',
'Show Less'				=>	'« Меньше',

'Spoiler'				=>	'скрытый текст'

];
