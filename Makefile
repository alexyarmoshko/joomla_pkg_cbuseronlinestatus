# Makefile for pkg_cbuseronlinestatus
# Builds plugin, module, and package ZIPs with update XML patching.

NAME         := cbuseronlinestatus
PLG_NAME     := plg_system_$(NAME)
MOD_NAME     := mod_$(NAME)
PKG_NAME     := pkg_$(NAME)

GITHUB_OWNER ?= alexyarmoshko
GITHUB_REPO  ?= joomla_pkg_cbuseronlinestatus

# Read version from the package manifest
VERSION      := $(shell grep -oP '(?<=<version>)[^<]+' $(PKG_NAME).xml)

PLG_DIR      := $(PLG_NAME)
MOD_DIR      := $(MOD_NAME)

PLG_ZIP      := $(PLG_NAME)-$(VERSION).zip
MOD_ZIP      := $(MOD_NAME)-$(VERSION).zip
PKG_ZIP      := $(PKG_NAME)-$(VERSION).zip

INST_DIR     := installation

.PHONY: info dist clean

info:
	@echo "Name:    $(PKG_NAME)"
	@echo "Version: $(VERSION)"
	@echo "Plugin:  $(PLG_ZIP)"
	@echo "Module:  $(MOD_ZIP)"
	@echo "Package: $(PKG_ZIP)"

dist: clean
	@mkdir -p $(INST_DIR)

	@echo "--- Building plugin ZIP ---"
	cp LICENSE $(PLG_DIR)/LICENSE
	cd $(PLG_DIR) && zip -r ../$(INST_DIR)/$(PLG_ZIP) . -x ".*"
	rm $(PLG_DIR)/LICENSE

	@echo "--- Building module ZIP ---"
	cp LICENSE $(MOD_DIR)/LICENSE
	cd $(MOD_DIR) && zip -r ../$(INST_DIR)/$(MOD_ZIP) . -x ".*"
	rm $(MOD_DIR)/LICENSE

	@echo "--- Updating plugin update XML ---"
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(INST_DIR)/$(PLG_ZIP)" || shasum -a 256 "$(INST_DIR)/$(PLG_ZIP)") | awk '{print $$1}' )"; \
	echo "Plugin SHA256:  $$SHA256"; \
	awk -v version="$(VERSION)" \
	    -v url="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/v$(VERSION)/$(PLG_ZIP)" \
	    -v sha="$$SHA256" '{ \
		if ($$0 ~ /<version>[^<]+<\/version>/) { \
			sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
		} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(PLG_NAME).update.xml" > "$(PLG_NAME).update.xml.tmp" && mv "$(PLG_NAME).update.xml.tmp" "$(PLG_NAME).update.xml"

	@echo "--- Updating module update XML ---"
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(INST_DIR)/$(MOD_ZIP)" || shasum -a 256 "$(INST_DIR)/$(MOD_ZIP)") | awk '{print $$1}' )"; \
	echo "Module SHA256:  $$SHA256"; \
	awk -v version="$(VERSION)" \
	    -v url="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/v$(VERSION)/$(MOD_ZIP)" \
	    -v sha="$$SHA256" '{ \
		if ($$0 ~ /<version>[^<]+<\/version>/) { \
			sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
		} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(MOD_NAME).update.xml" > "$(MOD_NAME).update.xml.tmp" && mv "$(MOD_NAME).update.xml.tmp" "$(MOD_NAME).update.xml"

	@echo "--- Building package ZIP ---"
	cp $(PKG_NAME).xml $(PKG_NAME).xml.bak
	sed -i 's/$(PLG_NAME)\.zip/$(PLG_ZIP)/g; s/$(MOD_NAME)\.zip/$(MOD_ZIP)/g' $(PKG_NAME).xml
	zip -r $(INST_DIR)/$(PKG_ZIP) \
		$(PKG_NAME).xml \
		$(INST_DIR)/$(PLG_ZIP) \
		$(INST_DIR)/$(MOD_ZIP) \
		language/ \
		LICENSE
	mv $(PKG_NAME).xml.bak $(PKG_NAME).xml

	@echo "--- Updating package update XML ---"
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(INST_DIR)/$(PKG_ZIP)" || shasum -a 256 "$(INST_DIR)/$(PKG_ZIP)") | awk '{print $$1}' )"; \
	echo "Package SHA256: $$SHA256"; \
	awk -v version="$(VERSION)" \
	    -v url="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/v$(VERSION)/$(PKG_ZIP)" \
	    -v sha="$$SHA256" '{ \
		if ($$0 ~ /<version>[^<]+<\/version>/) { \
			sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
		} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(PKG_NAME).update.xml" > "$(PKG_NAME).update.xml.tmp" && mv "$(PKG_NAME).update.xml.tmp" "$(PKG_NAME).update.xml"

	@echo ""
	@echo "=== Build complete ==="
	@echo "  $(INST_DIR)/$(PLG_ZIP)"
	@echo "  $(INST_DIR)/$(MOD_ZIP)"
	@echo "  $(INST_DIR)/$(PKG_ZIP)"

clean:
	rm -f $(INST_DIR)/$(PLG_ZIP) $(INST_DIR)/$(MOD_ZIP) $(INST_DIR)/$(PKG_ZIP)
