{capture assign='contentTitle'}{lang}wcf.igdb_integration.page.game_list_title{/lang} <span
	class="badge">{#$items}</span>{/capture}

{capture assign='headContent'}
	{if $pageNo < $pages}
		<link rel="next" href="{link controller='IgdbIntegrationGameList'}pageNo={unsafe:$pageNo+1}{/link}">
	{/if}
	{if $pageNo > 1}
		<link rel="prev" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 2}pageNo={unsafe:$pageNo-1}{/if}{/link}">
	{/if}
	<link rel="canonical" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 1}pageNo={unsafe:$pageNo}{/if}{/link}">
{/capture}

{capture assign='sidebarRight'}
	<section class="box">
		<form id="gameSortForm" method="post" action="{link controller='IgdbIntegrationGameList'}{/link}">
			<h2 class="boxTitle">{lang}wcf.global.filter{/lang}</h2>

			<div class="boxContent">
				<div class="igdbIntegrationSearchInfo">
					{if $showIgdbError}
						<p class="error">{lang}wcf.igdb_integration.page.game_list_igdb_error{/lang}</p>
					{else}
						{lang}wcf.igdb_integration.page.game_list_info{/lang}
					{/if}
				</div>
				<dl>
					<dt>{lang}wcf.global.title{/lang}</dt>
					<dd>
						<input type="text" id="searchField" name="searchField" value="{$searchField}">
						{event name='searchField'}
					</dd>
					<dt>{lang}wcf.igdb_integration.game.platforms{/lang}</dt>
					<dd>
						<select id="platformFilter" name="platforms[]" multiple size="6">
							{foreach from=$availablePlatforms item=platform}
								<option value="{$platform}"{if $platform|in_array:$platformFilter} selected{/if}>{$platform}</option>
							{/foreach}
						</select>
						{event name='platformFilter'}
					</dd>
					<dt>{lang}wcf.global.sorting{/lang}</dt>
					<dd>
						<select id="sortField" name="sortField">
							<option value="displayName" {if $sortField == 'displayName'} selected{/if}>
								{lang}wcf.igdb_integration.game.name{/lang}</option>
							<option value="releaseYear" {if $sortField == 'releaseYear'} selected{/if}>
								{lang}wcf.igdb_integration.game.year{/lang}</option>
							<option value="playerCount" {if $sortField == 'playerCount'} selected{/if}>
								{lang}wcf.igdb_integration.game.players{/lang}</option>
							<option value="averageRating" {if $sortField == 'averageRating'} selected{/if}>
								{lang}wcf.form.field.rating{/lang}</option>
							<option value="lastInteractionTime" {if $sortField == 'lastInteractionTime'} selected{/if}>
								{lang}wcf.igdb_integration.game.last_interaction{/lang}</option>
							{event name='sortField'}
						</select>
						<select name="sortOrder">
							<option value="ASC" {if $sortOrder == 'ASC'} selected{/if}>
								{lang}wcf.global.sortOrder.ascending{/lang}</option>
							<option value="DESC" {if $sortOrder == 'DESC'} selected{/if}>
								{lang}wcf.global.sortOrder.descending{/lang}</option>
						</select>
					</dd>
				</dl>

				<div class="formSubmit">
					<input type="submit" value="{lang}wcf.global.button.submit{/lang}" accesskey="s">
					<a href="{$resetUrl}" class="button">{lang}wcf.global.button.reset{/lang}</a>
				</div>
			</div>
		</form>
	</section>
	{if $importBoxAvailable}
		<section id="importBox" class="box">
			<details{if $steamImportAuthenticated || $steamImportError} open{/if}>
				<summary class="boxTitle">
					<h2>{lang}wcf.igdb_integration.page.import{/lang}</h2>
				</summary>
				<div class="boxContent">
					{if $steamImportAvailable}
						<h3>{lang}wcf.igdb_integration.page.steam_import{/lang}</h3>
						{if $steamImportError}
							<p class="error">{lang}wcf.igdb_integration.page.steam_import_error{/lang}</p>
						{/if}
						<p>{lang}wcf.igdb_integration.page.steam_import_info{/lang}</p>
						<div class="formSubmit">
							{if $steamImportAuthenticated}
								<button type="button" class="button buttonPrimary" id="steamImportButton">{lang}wcf.igdb_integration.page.steam_import_button{/lang}</button>
							{else}
								<a href="{$steamAuthUrl}" class="igdbIntegrationSteamSignInButton"><img src="{$__wcf->getPath()}images/igdbIntegration/signInThroughSteam.png" width="180" height="35" alt="{lang}wcf.igdb_integration.page.steam_import_sign_in{/lang}" title="{lang}wcf.igdb_integration.page.steam_import_sign_in{/lang}"></a>
							{/if}
						</div>
					{/if}
					{if $gogImportAvailable}
						<h3>{lang}wcf.igdb_integration.page.gog_import{/lang}</h3>
						<p>{lang}wcf.igdb_integration.page.gog_import_info{/lang}</p>
						<div class="formSubmit">
							<button type="button" class="button" id="gogImportButton">{lang}wcf.igdb_integration.page.gog_import_button{/lang}</button>
						</div>
					{/if}
					{if $playniteImportAvailable}
						<h3>{lang}wcf.igdb_integration.page.playnite_import{/lang}</h3>
						<p>{lang}wcf.igdb_integration.page.playnite_import_info{/lang}</p>
						<div class="formSubmit">
							<input type="file" id="playniteImportFileInput" accept=".csv,text/csv" style="display: none;">
							<button type="button" class="button" id="playniteImportButton">{lang}wcf.igdb_integration.page.playnite_import_button{/lang}</button>
						</div>
					{/if}
					{if $igdbImportAvailable}
						<h3>{lang}wcf.igdb_integration.page.igdb_import{/lang}</h3>
						<p>{lang}wcf.igdb_integration.page.igdb_import_info{/lang}</p>
						<div class="formSubmit">
							<input type="file" id="igdbImportFileInput" accept=".csv,text/csv" style="display: none;">
							<button type="button" class="button" id="igdbImportButton">{lang}wcf.igdb_integration.page.igdb_import_button{/lang}</button>
						</div>
					{/if}
				</div>
			</details>
		</section>
	{/if}
	<section id="playerToplistBox" class="box">
		<h2 class="boxTitle">{lang}wcf.igdb_integration.page.player_toplist{/lang}</h2>
		<div class="boxContent">
			<table>
				{foreach from=$topPlayers item=player}
					<tr>
						<td>
							<b>{unsafe:$topPlayerProfileLinks[$player['userId']]}</b>
						</td>
						<td>
							{$player['gameCount']} {lang}wcf.user.option.igdb_integration_game_count{/lang}
						</td>
					</tr>
				{/foreach}
			</table>
		</div>
	</section>
{/capture}

{include file='header'}

{hascontent}
<div class="paginationTop">
	{content}
	{pages print=true assign=pagesLinks controller='IgdbIntegrationGameList' link="pageNo=%d&searchField=$searchField&sortField=$sortField&sortOrder=$sortOrder$platformFilterParams"}
	{/content}
</div>
{/hascontent}

{if $items}
	<div class="section igdbIntegrationGameListContainer">
		{foreach from=$objects item=game}
			<div class="gameBox" id="gameBox{$game->gameId}">
				<div class="gameCover" style="background-image: url({$coverImageUrls[$game->gameId]});">
					<div class="gameOverlay" id="gameOverlay{$game->gameId}">
						{if $__wcf->user->userID && $__wcf->session->getPermission('user.igdb_integration.can_manage_own_games')}
							<span class="gameOverlayButton pointer" id="gameOverlayEdit{$game->gameId}"
								title="{lang}wcf.igdb_integration.dialog.game_user_edit_title{/lang}">
								{icon size=48 name='pen-to-square' type='solid'}
							</span>
							<span class="gameOverlayButton pointer" id="gameOverlayQuickAdd{$game->gameId}"
								data-is-owned="{if $game->isOwned == 1}1{else}0{/if}"
								title="{if $game->isOwned == 1}{lang}wcf.igdb_integration.page.game_quick_remove{/lang}{else}{lang}wcf.igdb_integration.page.game_quick_add{/lang}{/if}">
								{if $game->isOwned == 1}
									{icon size=48 name='minus' type='solid'}
								{else}
									{icon size=48 name='plus' type='solid'}
								{/if}
							</span>
						{else}
							<span class="gameOverlayButton pointer" id="gameOverlayEdit{$game->gameId}"
								title="{lang}wcf.igdb_integration.dialog.game_user_edit_title{/lang}">
								{icon size=48 name='circle-info' type='solid'}
							</span>
						{/if}
					</div>
				</div>
				<div class="gameInfo">
					<h3>{$game->displayName}</h3>
					<small>{$game->releaseYear}</small>
					<div class="gameUserInfo">
						<p class="gameAverageRating orange">
							{section name=ratingStars loop=$game->averageRating}{icon size=16 name='star' type='solid'}{/section}
						</p>
						<p class="gamePlayerCount pointer{if $game->isOwned == 1} isOwned{/if}"
							id="gamePlayerCount{$game->gameId}" {if $game->playerCount <= 0} style="display: none;" {/if}>
							{icon size=16 name='user' type='solid'} {$game->playerCount}
						</p>
					</div>
				</div>
			</div>
		{/foreach}
	</div>
{else}
	<p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

<footer class="contentFooter igdbIntegrationFooter">
	{hascontent}
	<div class="paginationBottom">
		{content}{unsafe:$pagesLinks}{/content}
	</div>
	{/hascontent}

	{hascontent}
	<nav class="contentFooterNavigation">
		<ul>{content}{event name='contentFooterNavigation'}{/content}</ul>
	</nav>
	{/hascontent}

	{if IGDB_INTEGRATION_GENERAL_SHOW_BRANDING}
		<div class="igdbIntegrationCopyright">{lang}wcf.igdb_integration.page.copyright_info{/lang}</div>
	{/if}
</footer>

<script data-relocate="true">
	require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameList'], (Language,
		ControllerIgdbIntegrationGameList) => {
		Language.addObject({
			'wcf.igdb_integration.dialog.game_user_edit_title': '{jslang}wcf.igdb_integration.dialog.game_user_edit_title{/jslang}',
			'wcf.igdb_integration.dialog.game_player_list_title': '{jslang}wcf.igdb_integration.dialog.game_player_list_title{/jslang}',
			'wcf.igdb_integration.page.game_quick_add': '{jslang}wcf.igdb_integration.page.game_quick_add{/jslang}',
			'wcf.igdb_integration.page.game_quick_remove': '{jslang}wcf.igdb_integration.page.game_quick_remove{/jslang}',
			'wcf.igdb_integration.dialog.game_quick_remove_confirm': '{jslang __literal=true}wcf.igdb_integration.dialog.game_quick_remove_confirm{/jslang}',
			'wcf.igdb_integration.dialog.game_quick_remove_confirm_indeterminate': '{jslang}wcf.igdb_integration.dialog.game_quick_remove_confirm_indeterminate{/jslang}'
		});
		{foreach from=$objects item=game}
			ControllerIgdbIntegrationGameList.init({unsafe:$game->gameId});
		{/foreach}
		{if $openGameId}
			ControllerIgdbIntegrationGameList.showGameUserEditDialog({unsafe:$openGameId});
		{/if}
	});
</script>

{if $importBoxAvailable}
	<script data-relocate="true">
		require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameImport'], (Language,
			ControllerIgdbIntegrationGameImport) => {
			Language.addObject({
				'wcf.igdb_integration.dialog.steam_import_title': '{jslang}wcf.igdb_integration.dialog.steam_import_title{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_confirm': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_confirm{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_empty': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_empty{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_request_failed': '{jslang}wcf.igdb_integration.dialog.steam_import_request_failed{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_progress_title': '{jslang}wcf.igdb_integration.dialog.steam_import_progress_title{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_progress_batches': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_progress_batches{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_progress_search': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_progress_search{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_progress_finalize': '{jslang}wcf.igdb_integration.dialog.steam_import_progress_finalize{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_result_title': '{jslang}wcf.igdb_integration.dialog.steam_import_result_title{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_result_failed': '{jslang}wcf.igdb_integration.dialog.steam_import_result_failed{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_result_imported': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_result_imported{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_result_already_owned': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_result_already_owned{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_result_ambiguous': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_result_ambiguous{/jslang}',
				'wcf.igdb_integration.dialog.steam_import_result_unmatched': '{jslang __literal=true}wcf.igdb_integration.dialog.steam_import_result_unmatched{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_title': '{jslang}wcf.igdb_integration.dialog.gog_import_title{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_username': '{jslang}wcf.igdb_integration.dialog.gog_import_username{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_confirm': '{jslang __literal=true}wcf.igdb_integration.dialog.gog_import_confirm{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_empty': '{jslang __literal=true}wcf.igdb_integration.dialog.gog_import_empty{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_request_failed': '{jslang __literal=true}wcf.igdb_integration.dialog.gog_import_request_failed{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_progress_title': '{jslang}wcf.igdb_integration.dialog.gog_import_progress_title{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_progress_pages': '{jslang __literal=true}wcf.igdb_integration.dialog.gog_import_progress_pages{/jslang}',
				'wcf.igdb_integration.dialog.gog_import_result_failed': '{jslang}wcf.igdb_integration.dialog.gog_import_result_failed{/jslang}',
				'wcf.igdb_integration.dialog.igdb_import_title': '{jslang}wcf.igdb_integration.dialog.igdb_import_title{/jslang}',
				'wcf.igdb_integration.dialog.igdb_import_confirm': '{jslang __literal=true}wcf.igdb_integration.dialog.igdb_import_confirm{/jslang}',
				'wcf.igdb_integration.dialog.igdb_import_invalid_file': '{jslang}wcf.igdb_integration.dialog.igdb_import_invalid_file{/jslang}',
				'wcf.igdb_integration.dialog.playnite_import_title': '{jslang}wcf.igdb_integration.dialog.playnite_import_title{/jslang}',
				'wcf.igdb_integration.dialog.playnite_import_confirm': '{jslang __literal=true}wcf.igdb_integration.dialog.playnite_import_confirm{/jslang}',
				'wcf.igdb_integration.dialog.playnite_import_invalid_file': '{jslang}wcf.igdb_integration.dialog.playnite_import_invalid_file{/jslang}',
				'wcf.igdb_integration.dialog.playnite_import_progress_title': '{jslang}wcf.igdb_integration.dialog.playnite_import_progress_title{/jslang}',
				'wcf.igdb_integration.dialog.import_result_copy': '{jslang}wcf.igdb_integration.dialog.import_result_copy{/jslang}',
				'wcf.igdb_integration.dialog.import_result_copy_success': '{jslang}wcf.igdb_integration.dialog.import_result_copy_success{/jslang}'
			});
			ControllerIgdbIntegrationGameImport.init({if $steamImportAutoOpen}true{else}false{/if}, '{unsafe:$steamGameListUrl|encodeJS}');
		});
	</script>
{/if}

{include file='footer'}