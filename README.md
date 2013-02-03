Chamilo Shell script
====================

The Chamilo Shell (or "Chash") is a command-line PHP tool meant to speed up the
management of (multiple) Chamilo portals under Linux.

To get the most out of Chash, you should add a link to it from your 
/usr/local/bin directory. You can do this getting inside the directory where
you put chash.php and doing:

  ln -s chash.php /usr/local/bin/chash

Then you can launch chash by moving into any Chamilo installation directory and
typing

  chash

It will give you the details of what command you can use to run it properly.

The most useful command to us until now has been the "chash sql_cli" command, 
which puts you directly into a MySQL client session.

Licensing
=========

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

Mail: info@chamilo.org

