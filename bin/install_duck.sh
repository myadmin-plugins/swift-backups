#!/bin/bash
if [ -e /cygdrive ] && [ ! -e /boot ]; then
	choco install duck;
elif [ -e /etc/apt ]; then
	echo -e "deb https://s3.amazonaws.com/repo.deb.cyberduck.io stable main" | sudo tee -a /etc/apt/sources.list > /dev/null
	sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys FE7097963FEFBE72
	sudo apt-get update
	sudo apt-get install duck
else
	echo -e "[duck-stable]\nname=duck-stable\nbaseurl=https://repo.cyberduck.io/stable/\$basearch/\nenabled=1\ngpgcheck=0" | sudo tee /etc/yum.repos.d/duck-stable.repo
	sudo yum install duck
fi;
