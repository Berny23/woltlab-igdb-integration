{capture assign='contentTitle'}{lang}wcf.igdb_integration.page.game_list_title{/lang} <span
	class="badge">{#$items}</span>{/capture}

{capture assign='headContent'}
	{if $pageNo < $pages}
		<link rel="next" href="{link controller='IgdbIntegrationGameList'}pageNo={@$pageNo+1}{/link}">
	{/if}
	{if $pageNo > 1}
		<link rel="prev" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 2}pageNo={@$pageNo-1}{/if}{/link}">
	{/if}
	<link rel="canonical" href="{link controller='IgdbIntegrationGameList'}{if $pageNo > 1}pageNo={@$pageNo}{/if}{/link}">
{/capture}

{capture assign='sidebarRight'}
	{if $showIgdbError}
		<section id="messageBox" class="box error">{lang}wcf.igdb_integration.page.game_list_igdb_error{/lang}</section>
	{else}
		<section id="messageBox" class="box info">{lang}wcf.igdb_integration.page.game_list_info{/lang}</section>
	{/if}
	<section class="box">
		<form id="gameSortForm" method="post" action="{link controller='IgdbIntegrationGameList'}{/link}">
			<h2 class="boxTitle">{lang}wcf.global.search{/lang}</h2>

			<div class="boxContent">
				<dl>
					<dt>{lang}wcf.global.name{/lang}</dt>
					<dd>
						<input type="text" id="searchField" name="searchField" value="{$searchField}">
						{event name='searchField'}
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
				</div>
			</div>
		</form>
	</section>
	<div class="box info">{lang}wcf.igdb_integration.page.copyright_info{/lang}</div>
{/capture}

{include file='header'}

{hascontent}
	<div class="paginationTop">
		{content}
			{pages print=true assign=pagesLinks controller='IgdbIntegrationGameList' link="pageNo=%d&searchField=$searchField&sortField=$sortField&sortOrder=$sortOrder"}
		{/content}
	</div>
{/hascontent}

{if $items}
	<div class="section igdbIntegrationGameListContainer">
		{foreach from=$objects item=game}
		<div class="gameBox" id="gameBox{$game->gameId}">
			<div class="gameCover" style="background-image: url({$coverImageUrls[$game->gameId]});">
				<ul class="gameOverlay pointer" id="gameOverlay{$game->gameId}">
					<span class="icon icon64 pointer fa-plus"></span>
				</ul>
			</div>
			<div class="gameInfo">
				<h3>{$game->displayName}</h3>
				<small>{if $game->releaseYear != 0}{$game->releaseYear}{/if}</small>
				<div class="gameUserInfo">
					<p class="gameAverageRating">
						{section name=ratingStars loop=$game->averageRating}<span
							class="icon icon16 fa-star orange"></span>{/section}
					</p>
					<p class="gamePlayerCount pointer{if $game->isOwned == 1} isOwned{/if}"
						id="gamePlayerCount{$game->gameId}" {if $game->playerCount <= 0} style="display: none;" {/if}>
						<span class="icon fa-user"></span> {$game->playerCount}
					</p>
				</div>
			</div>
		</div>
		{/foreach}
	</div>
{else}
<p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

<footer class="contentFooter">
	{hascontent}
	<div class="paginationBottom">
		{content}{@$pagesLinks}{/content}
	</div>
	{/hascontent}

	{hascontent}
	<nav class="contentFooterNavigation">
		<ul>{content}{event name='contentFooterNavigation'}{/content}</ul>
	</nav>
	{/hascontent}
</footer>

<script data-relocate="true">
	{foreach from=$objects item=game}
		require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameList'], (Language,
			ControllerIgdbIntegrationGameList) => {
			Language.addObject({
				'wcf.igdb_integration.dialog.game_user_edit_title': '{jslang}wcf.igdb_integration.dialog.game_user_edit_title{/jslang}',
				'wcf.igdb_integration.dialog.game_player_list_title': '{jslang}wcf.igdb_integration.dialog.game_player_list_title{/jslang}'
			})
			ControllerIgdbIntegrationGameList.init({@$game->gameId});
		});
	{/foreach}
</script>

{include file='footer'}