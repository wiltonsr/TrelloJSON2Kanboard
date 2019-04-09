plugin=TrelloJSON2Kanboard
version=$(shell grep -A2 getPluginVersion Plugin.php | grep return | cut -d \' -f2)

all:
	@ echo "Build archive for plugin ${plugin} version=${version}"
	@ git archive HEAD --prefix=${plugin}/ --format=zip -o ${plugin}-${version}.zip

tag:
	@ echo "Git Tagging plugin ${plugin} tag=${version}"
	@ git tag -a ${version} -m "Version ${version}"
	@ git push origin ${version}
