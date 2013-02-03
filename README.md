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
