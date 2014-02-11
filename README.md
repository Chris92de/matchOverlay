README:

0. About matchOverlay
1. Installation
2. How to use matchOverlay
3. Bug reports

-------------------------------------------------------------------------------------------------
-> 0. About matchOverlay
-------------------------------------------------------------------------------------------------

Livestreaming and broadcasting is a more and more important part of eSports. Every major 
eSports event is broadcasted and broadcasting is also up and coming in the Trackmania scene.

The only real thing that the spectator mode of Trackmania lacks is a nice overlay that displays
the current standings in Team mode. matchOverlay helps here!
Inspired by the plugin "tSplash" which was developed for ESL TV by svenstucki, matchOverlay is
meant to bring you a visually appealing overlay and even more useful features for broadcasters
in the future. Customization options will be added in an upcoming release, which will feature
a config file.

-------------------------------------------------------------------------------------------------
-> 1. Installation
-------------------------------------------------------------------------------------------------

- Extract the "plugin.mOverlay.php" to your XAseco "plugins" folder.
- Extract the "moverlay.xml" into the your XAseco folder.
- Add "<plugin>plugin.mOverlay.php</plugin>" to your "plugins.xml".
- Restart XAseco

-------------------------------------------------------------------------------------------------
-> 2. How to use matchOverlay
-------------------------------------------------------------------------------------------------

These commands require at least Admin permissions.

COMMANDS: 

/moverlay enable -> Activates the plugin & overlay. 

/moverlay disable -> Hides the overlay for everyone.

/moverlay to <all/players/spectators> -> Who should see the overlay? (Default: only Spectators)

/moverlay team <1/2> <team name> -> Lets you set names for the teams (1 = Blue team / 2 = Red team)

/moverlay team auto <1/2> -> Automatic Team name description. (1 = Blue team / 2 = Red team)

/moverlay mscore <Score1> <Score2> -> With this command you can set the overall score of the match. (Will be automated in a future release)

/moverlay about -> Displays a bit of information about the plugin

-------------------------------------------------------------------------------------------------
-> 3. Bug reports & Feature Requests
-------------------------------------------------------------------------------------------------

In case you encounter any bugs, report an issue over on [GitHub](http://github.com/Chris92de/matchOverlay).
You can also request features there.
