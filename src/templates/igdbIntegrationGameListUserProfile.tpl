{if $userGames}
	<div class="status info">{lang}wcf.user.option.igdb_integration_game_count{/lang}: {$gameCount}</div>
	<br />
	<div class="section igdbIntegrationGameListContainer">
		{foreach from=$userGames item=game}
			<div class="gameBox" id="gameBox{$game['gameId']}">
				<div class="gameCover" style="background-image: url({$game['coverImageUrl']});">
					<ul class="gameOverlay pointer" id="gameOverlay{$game['gameId']}">
						<span class="icon icon64 pointer fa-plus"></span>
					</ul>
				</div>
				<div class="gameInfo">
					<h3>{$game['displayName']}</h3>
					<small>{if $game['releaseYear'] != 0}{$game['releaseYear']}{/if}</small>
					<div class="gameUserInfo">
						<p class="gameOwnRating">
							{section name=ratingStars loop=$game['ownRating']}<span
								class="icon icon16 fa-star orange"></span>{/section}
						</p>
						<p class="gamePlayerCount {if $game['isOwned'] == 1} isOwned{/if}" id="gamePlayerCount{$game['gameId']}"
							{if $game['playerCount'] <= 0} style="display: none;" {/if}>
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
	<div class="igdbIntegrationCopyright">{lang}wcf.igdb_integration.page.copyright_info{/lang}</div>
</footer>

<script data-relocate="true">
	{foreach from=$userGames item=game}
		require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile'], (Language,
			ControllerIgdbIntegrationGameListUserProfile) => {
			Language.addObject({
				'wcf.igdb_integration.dialog.game_user_edit_title': '{jslang}wcf.igdb_integration.dialog.game_user_edit_title{/jslang}'
			});

			ControllerIgdbIntegrationGameListUserProfile.init({@$game['gameId']}, {$userId});
		});
	{/foreach}
</script>