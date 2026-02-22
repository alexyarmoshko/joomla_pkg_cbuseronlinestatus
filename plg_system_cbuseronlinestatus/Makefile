# Makefile for plg_system_cbuseronlinestatus — Joomla system plugin
#
# Build the installable ZIP into installation/.
# Usage:
#   make info    — show plugin metadata
#   make dist    — build ZIP and update manifest (if update XML exists)
#   make clean   — remove the built ZIP
#
# @author      Yak Shaver <me@kayakshaver.com>
# @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
# @license     GNU General Public License version 2 or later; see LICENSE.txt

PLUGIN_NAME := cbuseronlinestatus
PLUGIN_TYPE := system
PLUGIN_MANIFEST_XML := cbuseronlinestatus.xml
PLUGIN_UPDATE_XML := plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME).update.xml
INSTALL_DIR ?= ../installation
LICENSE_FILE ?= ../LICENSE

PLUGIN_VERSION := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' "$(PLUGIN_MANIFEST_XML)")
ZIP_NAME := plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip
ZIP_PATH := $(INSTALL_DIR)/$(ZIP_NAME)

GITHUB_OWNER ?= alexyarmoshko
GITHUB_REPO ?= joomla_pkg_cbuseronlinestatus
GITHUB_REF ?= v$(PLUGIN_VERSION)

PLUGIN_FILES := $(PLUGIN_MANIFEST_XML) services/ src/ language/ index.html

.PHONY: info dist clean

info:
	@echo "Plugin:          plg_$(PLUGIN_TYPE)_$(PLUGIN_NAME)"
	@echo "Plugin version:  $(PLUGIN_VERSION)"
	@echo "Source:          $(PLUGIN_FILES)"
	@echo "Package output:  $(ZIP_PATH)"
	@echo "Manifest output: $(PLUGIN_UPDATE_XML)"

dist: $(ZIP_PATH)
	@SHA256="$$( (command -v sha256sum >/dev/null && sha256sum "$(ZIP_PATH)" || shasum -a 256 "$(ZIP_PATH)") | awk '{print $$1}' )"; \
	if [ -f "$(PLUGIN_UPDATE_XML)" ]; then \
		DOWNLOAD_URL="https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(GITHUB_REF)/$(ZIP_NAME)"; \
		awk -v version="$(PLUGIN_VERSION)" -v url="$$DOWNLOAD_URL" -v sha="$$SHA256" '{ \
			if ($$0 ~ /<version>[^<]+<\/version>/) { \
				sub(/<version>[^<]+<\/version>/, "<version>" version "</version>"); \
			} else if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
				sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
			} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
				sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
			} \
			print; \
		}' "$(PLUGIN_UPDATE_XML)" > "$(PLUGIN_UPDATE_XML).tmp" && mv "$(PLUGIN_UPDATE_XML).tmp" "$(PLUGIN_UPDATE_XML)"; \
		echo "Updated $(PLUGIN_UPDATE_XML)"; \
	else \
		echo "Skipped manifest update; $(PLUGIN_UPDATE_XML) not found"; \
	fi

$(ZIP_PATH):
	@mkdir -p "$(INSTALL_DIR)"
	@rm -f "$(ZIP_PATH)"
	@zip -qr -X "$(ZIP_PATH)" $(PLUGIN_FILES) -x "*.DS_Store" -x "*/.DS_Store"
	@zip -qj -X "$(ZIP_PATH)" "$(LICENSE_FILE)"
	@echo "Built $(ZIP_PATH)"

clean:
	@rm -f "$(ZIP_PATH)"
