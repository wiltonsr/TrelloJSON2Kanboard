TrelloJSON2Kanboard
==============================

Plugin for Importing Trello Projects from JSON Files to Kanboard.

Donate to help keep this project maintained.
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=5QJ62BNMRC75W&currency_code=USD&source=url">
<img src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" /></a>


Author
------

- Wilton Rodrigues
- License MIT

Requirements
------------

- Kanboard >= 1.0.35
- PHP curl Extension

Installation
------------

You have the choice between 2 methods:

1. Download the zip file and decompress everything under the directory `plugins/TrelloJSON2Kanboard`
2. Clone this repository into the folder `plugins/TrelloJSON2Kanboard`

Note: Plugin folder is case-sensitive.

When the plugin is installed, the option "Import Trello JSON" appears on the top menu of the main Kanboard dashboard:

![Import Trello JSON](images/import.png)

Exporting JSON Data From Trello
-------------------------------

Each Trello board needs to be exported individually. These screenshots illustrate the process (this reflects the Trello web UI as of August, 2021).

First, open a board and click the "... Show menu" button:

![Trello - Show Menu](images/1-trello.png)

On the menu, expand the "... More" option:

![Trello - Menu - More](images/2-trello.png)

Select the option to "Print and export":

![Trello - Print and export](images/3-trello.png)

From the "Print and export" menu, select the option to "Export as JSON":

![Trello - Export as JSON](images/4-trello.png)

This will deliver a raw JSON dump via the web browser. Right click in the browser and select "Save as..." in order to download from a file:

![Trello - Export JSON - Save as](images/5-trello.png)

After downloading this JSON file, use the new "Import Trello JSON" option on the main Kanboard dashboard to import the Trello board.
