{*{hascontent}
<div class="paginationTop">
    {content}
    {pages print=true assign=pagesLinks controller='IgdbIntegrationGameList' link="pageNo=%d"}
    {/content}
</div>
{/hascontent}*}

{if $userGames}
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
                    <small>{if $game['firstReleaseDateYear'] != 0}{$game['firstReleaseDateYear']}{/if}</small>
                    <div class="gameUserInfo">
                        <p class="gameOwnRating">
                            {section name=ratingStars loop=$game['ownRating']}
                                <span class="icon icon16 fa-star orange"></span>
                            {/section}
                        </p>
                        <p class="gamePlayerCount {if $game['isOwned'] == 1} isOwned{/if}" id="gamePlayerCount{$game['gameId']}"
                            {if $game['playerCount'] <= 0} style="display: none;" {/if}>
                        </p>
                    </div>
                </div>
            </div>
            <script>
                require(['WoltLabSuite/Core/Form/Builder/Dialog'], function(FormBuilderDialog) {
                var dialog = new FormBuilderDialog(
                    'gameUserEditDialog{$game['gameId']}',
                    'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction',
                    'getGameUserEditDialog', {
                        destroyOnClose: true,
                        actionParameters: {
                            gameId: {$game['gameId']},
                            userId: {$userId}
                        },
                        dialog: {
                            title: '{lang}wcf.IgdbIntegration.dialog.game_user_edit_title{/lang}'
                        },
                        submitActionName: 'submitGameUserEditDialog',
                        successCallback(returnValues) {
                            if (returnValues.playerCount <= 0) {
                                // Remove game from profile list
                                document.getElementById('gameBox' + returnValues.gameId).remove();
                            } else {
                                // Insert returned values into page
                                var html = '<p class="gameOwnRating">';

                                for (let i = 0; i < returnValues.ownRating; i++) {
                                    html += '<span class="icon icon16 fa-star orange"></span>';
                                }

                                html += '</p><p class="gamePlayerCount pointer';
                                if (returnValues.isOwned) {
                                    html += ' isOwned';
                                }
                                html += '" id="gamePlayerCount' + returnValues.gameId + '"></p>';

                                document.querySelector('#gameBox' + returnValues.gameId + ' .gameUserInfo')
                                    .innerHTML = html;
                            }
                        }
                    }
                );

                document.getElementById('gameOverlay{$game['gameId']}').addEventListener('click', function() {
                dialog.open();
                });
                });
            </script>
        {/foreach}
    </div>
{else}
    <p class="info">{lang}wcf.global.noItems{/lang}</p>
{/if}

{*<footer class="contentFooter">
    {hascontent}
    <div class="paginationBottom">
        {content}{@$pagesLinks}{/content}
    </div>
    {/hascontent}

    {hascontent}
    <nav class="contentFooterNavigation">
        <ul>
            {content}{event name='contentFooterNavigation'}{/content}
        </ul>
    </nav>
    {/hascontent}
</footer>*}

<style>
    .igdbIntegrationGameListContainer {
        display: flex;
        flex-wrap: wrap;
    }

    .igdbIntegrationGameListContainer .gameBox {
        display: flex;
        flex-direction: column;
        min-height: 18rem;
        width: 132px;
        margin: 0.25rem !important;
        border: 1px solid #ecf1f7;
        background-color: #FAFAFA;
        border-radius: 0.25rem;
    }

    .igdbIntegrationGameListContainer .gameBox .gameCover {
        height: 185px;
        border-top-left-radius: 0.25rem;
        border-top-right-radius: 0.25rem;
        background-repeat: no-repeat;
        background-position: center;
    }

    .igdbIntegrationGameListContainer .gameBox .gameCover .gameOverlay {
        display: flex;
        justify-content: center;
        height: 100%;
        align-items: center;
        background: rgba(0, 0, 0, 0.5);
        border-top-left-radius: 0.25rem;
        border-top-right-radius: 0.25rem;
        opacity: 0;
        transition: 0.2s;
    }

    .igdbIntegrationGameListContainer .gameBox .gameCover:hover .gameOverlay {
        opacity: 1;
    }

    .igdbIntegrationGameListContainer .gameBox .gameCover .gameOverlay .icon {
        color: #F5F5F5;
    }

    .igdbIntegrationGameListContainer .gameBox .gameInfo {
        display: flex;
        flex-direction: column;
        flex: 1;
        text-align: center;
        padding-top: 0.5rem;
    }

    .igdbIntegrationGameListContainer .gameBox .gameInfo>h3 {
        padding-left: 0.25rem;
        padding-right: 0.25rem;
    }

    .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo {
        margin-top: auto;
    }

    .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo .gamePlayerCount {
        padding: 0.25rem;
        padding-right: 0.5rem;
        text-align: end;
        transition: filter 0.2s;
        background-color: #B0BEC5;
    }

    .igdbIntegrationGameListContainer .gameBox .gameInfo .gameUserInfo .gamePlayerCount.isOwned {
        background-color: #A5D6A7;
    }
</style>