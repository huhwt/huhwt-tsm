
# webtrees module huhwt-tagging-service-manager

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)
![webtrees major version](https://img.shields.io/badge/webtrees-v2.2-green)

![Latest Release](https://img.shields.io/github/v/release/huhwt/huhwt-tsm)
[![Downloads](https://img.shields.io/github/downloads/huhwt/huhwt-tsm/total)]()

This [webtrees](https://www.webtrees.net/) custom module is an add-on to huhwt-cce , the clippings-cart-enhanced. 

This is a webtrees 2.2 module - It cannot be used with webtrees 2.1.

For webtrees 2.1 use the latest release of the huhwt-tsm Branch 2.1.

Attention:
~~~
  This module requires to be operated in a PHP 8.3-onward system.
~~~

## Contents
This Readme contains the following main sections

* [Description](#description)
* [Requirements](#requirements)
* [Installation](#installation)
* [Upgrade](#upgrade)
* [Translation](#translation)
* [Contact Support](#support)
* [Thank you](#thanks)
* [License](#license)

<a name="description"></a>
## Description

Gedcom knows the NOTE structure element. It may be subordinate to another structure, in which case it may contain specific information and additions to this structure. However, it can also exist independently, in which case it contains relevant information and this NOTE element is referenced from other structures. Such top-level NOTEs are referred to as 'shared notes' in webtrees and are treated separately in various places. (Gedcom 7 consequently renames it SNOTE, NOTE is then only available as a subordinate element).

If such NOTEs are structured according to a defined scheme, then additional information dimensions can be created, for example by assigning a corresponding NOTE to all people in a lineage and from then on they can be directly selected using this characteristic.

For a more detailed description of the functionality, please refer as yet to README.DE.MD (german language).

<a name="requirements"></a>
## Requirements

This module requires **PHP 8.3** at least.
This module requires **webtrees** version 2.2.0.0 at least.
This module has the same general requirements as [webtrees#system-requirements](https://github.com/fisharebest/webtrees#system-requirements).

<a name="installation"></a>
## Installation

This section documents installation instructions for this module.

1. Download the [latest release](https://github.com/huhwt/huhwt-tsm/releases/latest). (By now: pre-release!).
3. Unzip the package into your `webtrees/modules_v4` directory of your web server.
4. Occasionally rename the folder to `huhwt-tsm`. It's recommended to remove the respective directory if it already exists.

<a name="upgrade"></a>
## Upgrade

To update simply replace the huhwt-tsm files with the new ones from the latest release.

<a name="translation"></a>
## Translation

You can help to translate this module.
It uses the po/mo system.
You can contribute via a pull request (if you know how) or by e-mail.
Updated translations will be included in the next release of this module.

There are only translations in German available.

<a name="support"></a>
## Support

<span style="font-weight: bold;">Issues: </span>you can report errors raising an issue
in this GitHub repository.

<span style="font-weight: bold;">Forum: </span>general webtrees support can be found 
at the [webtrees forum](http://www.webtrees.net/)

<a name="thanks"></a>
## Thank you

Special thanks to [hartenthaler](https://github.com/hartenthaler/) for the basic suggestion.

<a name="license"></a>
## License

* Copyright (C) 2023-2025 huhwt - EW.H
* Derived from **webtrees** - Copyright 2024-2025 webtrees development team.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.

* * *
