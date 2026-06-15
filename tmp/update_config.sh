#!/bin/sh
cd /www
echo "# 资源配置" >> .env
echo "RESOURCE_PROFILE=minimal" >> .env
echo "ENABLE_AUTO_BACKUP_AND_UPDATE=true" >> .env
docker restart xboard