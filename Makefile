# Reproducible packaging for pkg_cbuseronlinestatus: the ZIP bytes depend only on
# the packaged files and one timestamp, never on the machine that built it, so a
# release can be rebuilt from its tag and still hash to the sha256 its update
# descriptor claims. Adapted from ys_toolbox's joomla_release_workflow template;
# the package builds its two child ZIPs first and ships them inside its own.
# The workflow is in README.md, section "Building from Source".
#
# @author      Yak Shaver <me@kayakshaver.com>
# @copyright   (C) 2026 Yak Shaver https://www.kayakshaver.com
# @license     GNU General Public License version 2 or later; see LICENSE

SHELL := /bin/sh

EXT_NAME := pkg_cbuseronlinestatus
MANIFEST := $(EXT_NAME).xml
GITHUB_OWNER := alexyarmoshko
GITHUB_REPO := joomla_pkg_cbuseronlinestatus

PLG_DIR := plg_system_cbuseronlinestatus
PLG_MANIFEST := $(PLG_DIR)/cbuseronlinestatus.xml
MOD_DIR := mod_cbuseronlinestatus
MOD_MANIFEST := $(MOD_DIR)/mod_cbuseronlinestatus.xml

# One explicit list per archive, relative to that archive's root. Never a directory.
# LICENSE comes from the repository root into every archive.
PLG_FILES := \
	LICENSE \
	cbuseronlinestatus.xml \
	index.html \
	services/provider.php \
	src/Extension/CbUserOnlineStatus.php \
	src/Field/OnlineTimeoutField.php \
	src/Field/StatusField.php \
	src/Field/UpstreamHashesField.php \
	src/Table/MessageTable.php \
	language/en-GB/plg_system_cbuseronlinestatus.ini \
	language/en-GB/plg_system_cbuseronlinestatus.sys.ini

MOD_FILES := \
	LICENSE \
	mod_cbuseronlinestatus.xml \
	index.html \
	services/provider.php \
	src/Dispatcher/Dispatcher.php \
	src/Field/RuntimeTimeoutField.php \
	src/Helper/CbUserOnlineStatusHelper.php \
	tmpl/default.php \
	tmpl/default_census.php \
	tmpl/default_statistics.php \
	language/en-GB/mod_cbuseronlinestatus.ini \
	language/en-GB/mod_cbuseronlinestatus.sys.ini

# The child ZIP names are the ones $(MANIFEST) lists under <files folder="installation">.
PKG_FILES := \
	LICENSE \
	$(MANIFEST) \
	language/en-GB/pkg_cbuseronlinestatus.sys.ini \
	installation/$(PLG_DIR).zip \
	installation/$(MOD_DIR).zip

JZIP := tools/jzip.php
ZIP_LEVEL := 9
INSTALL_DIR ?= installation
BUILD_DIR ?= build

RELEASE_NOTES := docs/RELEASE.md
UPDATE_TEMPLATE := $(EXT_NAME).update.xml
SHA256_PLACEHOLDER := 0000000000000000000000000000000000000000000000000000000000000000

# Every file whose <version> must equal the package's.
VERSIONED_MANIFESTS := $(MANIFEST) $(PLG_MANIFEST) $(MOD_MANIFEST) $(UPDATE_TEMPLATE)

# `override` is load-bearing. The tag, the ZIP name and the download URL all derive
# from this, and deriving it from the manifest instead of typing it is the whole point
# of `release`. Without `override`, `make release VERSION=x` tags a version the manifest
# never declared - and refusing to package it afterwards does not remove the tag.
override VERSION := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' $(MANIFEST))
ZIP_NAME := $(EXT_NAME)-v$(subst .,-,$(VERSION)).zip
RELEASE_STAGE := $(BUILD_DIR)/release
DEV_STAGE := $(BUILD_DIR)/dev
RELEASE_ZIP := $(INSTALL_DIR)/release/$(ZIP_NAME)
DEV_ZIP := $(INSTALL_DIR)/dev/$(ZIP_NAME)
UPDATE_ARTIFACT := $(INSTALL_DIR)/release/$(UPDATE_TEMPLATE)
DOWNLOAD_URL := https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(VERSION)/$(ZIP_NAME)

# No Composer and no test suite here: `test` says so rather than passing silently.
# These are the repository's own gates, and `release` runs them before it tags, so
# they are `:=`: an environment variable of the same name must not replace them.
DEPS_CMD := echo "No dependencies to install - the build needs only make, git and php."
TEST_CMD := echo "No automated tests in this repository - lint is the only gate."
# $(JZIP) is linted although it does not ship: it is the packager, and a syntax
# error in it would otherwise surface only after `release` has created the tag.
LINT_CMD := set -e; \
	for f in $(filter %.php,$(addprefix $(PLG_DIR)/,$(PLG_FILES)) $(addprefix $(MOD_DIR)/,$(MOD_FILES))) $(JZIP); do \
		php -l "$$f" >/dev/null || exit 1; \
	done; \
	for f in $(VERSIONED_MANIFESTS); do \
		php -r 'libxml_use_internal_errors(true); if (simplexml_load_file($$argv[1]) === false) { fwrite(STDERR, "FAIL: malformed XML in $$argv[1]\n"); exit(1); }' "$$f" || exit 1; \
	done; \
	echo "lint: ok"

sha256 = $$( { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$(1)"; else shasum -a 256 "$(1)"; fi; } | awk '{print $$1}' | grep -Ex '[0-9a-f]{64}' )

# Package the files $(3), relative to staging directory $(2), into $(1), stamping
# every entry with the epoch in $$stamp, which the caller sets once per build.
define package
	for f in $(3); do \
		if [ ! -f "$(2)/$$f" ]; then \
			echo "FAIL: $$f is in the file list for $(notdir $(1)) but not in $(2)/ - the staged tree is incomplete"; \
			exit 1; \
		fi; \
	done; \
	mkdir -p "$(dir $(1))"; \
	archive="$$(cd "$(dir $(1))" && pwd)/$$(basename "$(1)").part"; \
	rm -f "$(1).part"; \
	if [ -f "$(JZIP)" ]; then \
		php "$(JZIP)" --level=$(ZIP_LEVEL) "$$archive" "$$stamp" "$(2)" $(3) >/dev/null; \
		how="jzip level $(ZIP_LEVEL)"; \
	else \
		echo "WARNING: jzip not found at $(JZIP) - falling back to zip." >&2; \
		echo "WARNING: the package is NOT reproducible - zip stores this machine's mtimes and host metadata." >&2; \
		( cd "$(2)" && zip -q -X -$(ZIP_LEVEL) "$$archive" $(3) ); \
		how="zip fallback"; \
	fi; \
	mv "$(1).part" "$(1)"; \
	echo "$(1): $(words $(3)) entries, $$(wc -c < "$(1)") bytes, $$how, sha256 $(call sha256,$(1))"
endef

# Copy the files $(3), relative to source directory $(2), into staging directory $(1).
# LICENSE always comes from the source root $(4).
define stage_files
	for f in $(3); do \
		mkdir -p "$(1)/$$(dirname "$$f")"; \
		if [ "$$f" = LICENSE ]; then cp "$(4)/LICENSE" "$(1)/LICENSE"; else cp "$(2)/$$f" "$(1)/$$f"; fi; \
	done
endef

# Stage the three archives from source root $(1) under staging root $(2), then build
# both child ZIPs into the package tree and the package into $(3), all stamped $(4).
define build_package
	@set -e; \
	stamp="$(4)"; \
	$(call stage_files,$(2)/plg,$(1)/$(PLG_DIR),$(PLG_FILES),$(1)); \
	$(call stage_files,$(2)/mod,$(1)/$(MOD_DIR),$(MOD_FILES),$(1)); \
	$(call stage_files,$(2)/pkg,$(1),$(filter-out installation/%,$(PKG_FILES)),$(1)); \
	$(5) \
	$(call package,$(2)/pkg/installation/$(PLG_DIR).zip,$(2)/plg,$(PLG_FILES)); \
	$(call package,$(2)/pkg/installation/$(MOD_DIR).zip,$(2)/mod,$(MOD_FILES)); \
	$(call package,$(3),$(2)/pkg,$(PKG_FILES))
endef

# Refuse unless every manifest under source root $(1) declares $(VERSION). For a
# release $(1) is the tag export, so a tag whose manifests disagree with the
# working-tree package manifest fails here too.
define check_versions
	@set -e; \
	for m in $(VERSIONED_MANIFESTS); do \
		declared="$$(awk -F'[<>]' '/<version>/{print $$3; exit}' "$(1)/$$m")"; \
		if [ "$$declared" != "$(VERSION)" ]; then \
			echo "FAIL: $(1)/$$m declares version '$$declared', but the package is $(VERSION) from $(MANIFEST)"; \
			exit 1; \
		fi; \
	done
endef

# Shell fragment for dev builds: drop <updateservers> from the staged package manifest
# under $(1) and fail if any survives, so a test install cannot register the release
# update site. grep exits 2 on an unreadable file, which an `if` cannot tell from "no match".
define strip_updateservers
	sed '/<updateservers>/,/<\/updateservers>/d' "$(1)/pkg/$(MANIFEST)" > "$(1)/pkg/$(MANIFEST).tmp"; \
	mv "$(1)/pkg/$(MANIFEST).tmp" "$(1)/pkg/$(MANIFEST)"; \
	rc=0; grep -q "updateservers" "$(1)/pkg/$(MANIFEST)" || rc=$$?; \
	if [ "$$rc" -eq 0 ]; then echo "FAIL: <updateservers> survived in the dev manifest"; exit 1; \
	elif [ "$$rc" -gt 1 ]; then echo "FAIL: cannot read $(1)/pkg/$(MANIFEST) to confirm <updateservers> was stripped"; exit 1; fi;
endef

# The only variables release, dist_release and update_manifest take from the command
# line: where they write. Every other variable decides the tag, the published bytes,
# the descriptor or which gates run, so it must come from the Makefile.
override RELEASE_FREE := INSTALL_DIR BUILD_DIR

# Variables set on the command line, passed down from a parent make, or taken from the
# environment under make -e, less RELEASE_FREE. Declared override so the command line
# cannot empty it.
override release_overrides = $(sort $(filter-out $(RELEASE_FREE),$(foreach v,$(.VARIABLES),$(if $(findstring command line,$(origin $(v)))$(findstring environment override,$(origin $(v))),$(v)))))

override define refuse_release_overrides
	@if [ -n "$(release_overrides)" ]; then \
		echo "FAIL: $(release_overrides) set outside the Makefile"; \
		echo "      a release takes only $(RELEASE_FREE) from the command line; everything else"; \
		echo "      must come from the tagged Makefile. Change it in the repository instead."; \
		exit 1; \
	fi
endef

# Refuse a dirty working tree, or only the paths $(2) when given; $(1) says why it
# matters to the caller.
define require_clean_tree
	@set -e; \
	dirty="$$(git status --porcelain $(if $(2),-- $(2)))" || { echo "FAIL: git status failed - cannot confirm the tree is clean"; exit 1; }; \
	if [ -n "$$dirty" ]; then \
		echo "FAIL: working tree is dirty - $(1)"; \
		printf '%s\n' "$$dirty"; \
		exit 1; \
	fi
endef

# Refuse unless tag $(VERSION) exists and HEAD is that tag. Only the payload comes from
# the tag; the file lists, the packager, the compression level and the descriptor
# template are read from the checkout, so a release is reproducible from the tag only
# when the checkout IS the tag.
define require_tag_head
	@if ! git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: no tag $(VERSION) - run 'make release' first"; \
		exit 1; \
	fi
	@set -e; \
	head="$$(git rev-parse HEAD)"; \
	tagged="$$(git rev-parse "$(VERSION)^{commit}")"; \
	if [ "$$head" != "$$tagged" ]; then \
		echo "FAIL: HEAD is not tag $(VERSION)"; \
		echo "      HEAD $$head"; \
		echo "      tag  $$tagged"; \
		echo "      building here would use this checkout's build inputs against the tag's payload,"; \
		echo "      producing bytes a clean checkout of $(VERSION) cannot reproduce. Check the tag out."; \
		exit 1; \
	fi
endef

.PHONY: info deps test lint versions release dist_release dist_dev update_manifest clean

info:
	@echo "Package:         $(EXT_NAME)"
	@echo "Version:         $(VERSION)"
	@echo "Release package: $(RELEASE_ZIP)"
	@echo "Dev package:     $(DEV_ZIP)"
	@echo "Update template: $(UPDATE_TEMPLATE)"
	@echo "Update artifact: $(UPDATE_ARTIFACT)"
	@echo "Download URL:    $(DOWNLOAD_URL)"
	@echo "Packager:        $(JZIP) (level $(ZIP_LEVEL))"

deps:
	@$(DEPS_CMD)

test:
	@$(TEST_CMD)

lint:
	@$(LINT_CMD)

versions:
	$(call check_versions,.)
	@echo "versions: every manifest declares $(VERSION)"

release:
	$(refuse_release_overrides)
	$(call require_clean_tree,commit or stash before tagging)
	@if git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: tag $(VERSION) already exists - bump <version> in $(MANIFEST)"; \
		exit 1; \
	fi
	@if ! awk -v v="$(VERSION)" '/^## /{ if ($$2==v && tolower($$0) !~ /unreleased/) found=1 } END{ exit !found }' "$(RELEASE_NOTES)"; then \
		echo "FAIL: $(RELEASE_NOTES) has no '## $(VERSION)' heading, or it still says unreleased -"; \
		echo "      finish the release notes before tagging"; \
		exit 1; \
	fi
	$(call check_versions,.)
	$(MAKE) test
	$(MAKE) lint
	git tag -a -m "Release $(VERSION)" "$(VERSION)"
	@echo "Tagged $(VERSION) - next: make dist_release"

dist_release:
	$(refuse_release_overrides)
	$(call require_clean_tree,a release must be built from a clean checkout of $(VERSION))
	$(require_tag_head)
	@# The package macro degrades to `zip` when the packager is missing. Tolerable for a
	@# dev package, never for a published one.
	@if [ ! -f "$(JZIP)" ]; then \
		echo "FAIL: $(JZIP) is missing - a release package must come from the deterministic"; \
		echo "      packager, never the zip fallback. Restore it before building."; \
		exit 1; \
	fi
	@rm -f "$(RELEASE_ZIP)" "$(UPDATE_ARTIFACT)"
	@rm -rf "$(RELEASE_STAGE)"
	@mkdir -p "$(RELEASE_STAGE)/src"
	@git -c core.autocrlf=false archive "$(VERSION)" | tar -x -C "$(RELEASE_STAGE)/src"
	$(call check_versions,$(RELEASE_STAGE)/src)
	$(call build_package,$(RELEASE_STAGE)/src,$(RELEASE_STAGE)/stage,$(RELEASE_ZIP),$$(git log -1 --format=%ct "$(VERSION)"),)
	@rm -rf "$(RELEASE_STAGE)"
	$(MAKE) update_manifest

dist_dev:
	@rm -f "$(DEV_ZIP)"
	@rm -rf "$(DEV_STAGE)"
	$(call check_versions,.)
	$(call build_package,.,$(DEV_STAGE),$(DEV_ZIP),$$(git log -1 --format=%ct),$(call strip_updateservers,$(DEV_STAGE)))
	@rm -rf "$(DEV_STAGE)"

update_manifest:
	$(refuse_release_overrides)
	$(call require_clean_tree,the descriptor template must be the tagged one,$(UPDATE_TEMPLATE) Makefile)
	$(require_tag_head)
	@set -e; \
	if [ "$$(grep -oF "<sha256>$(SHA256_PLACEHOLDER)</sha256>" "$(UPDATE_TEMPLATE)" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_TEMPLATE) is not a template - <sha256> must be the $(SHA256_PLACEHOLDER) placeholder"; \
		exit 1; \
	fi; \
	if [ ! -f "$(RELEASE_ZIP)" ]; then \
		echo "FAIL: $(RELEASE_ZIP) is missing - the descriptor must describe a built package"; \
		exit 1; \
	fi; \
	SHA256="$(call sha256,$(RELEASE_ZIP))"; \
	if [ -z "$$SHA256" ]; then \
		echo "FAIL: cannot compute the sha256 of $(RELEASE_ZIP)"; \
		exit 1; \
	fi; \
	mkdir -p "$(dir $(UPDATE_ARTIFACT))"; \
	awk -v url="$(DOWNLOAD_URL)" -v sha="$$SHA256" '{ \
		if ($$0 ~ /<downloadurl[^>]*>[^<]+<\/downloadurl>/) { \
			sub(/<downloadurl[^>]*>[^<]+<\/downloadurl>/, "<downloadurl type=\"full\" format=\"zip\">" url "</downloadurl>"); \
		} else if ($$0 ~ /<sha256>[^<]+<\/sha256>/) { \
			sub(/<sha256>[^<]+<\/sha256>/, "<sha256>" sha "</sha256>"); \
		} \
		print; \
	}' "$(UPDATE_TEMPLATE)" > "$(UPDATE_ARTIFACT).part"; \
	if [ "$$(grep -oF "<sha256>$$SHA256</sha256>" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) does not carry exactly one <sha256> for $(RELEASE_ZIP)"; \
		rm -f "$(UPDATE_ARTIFACT).part"; exit 1; \
	fi; \
	if [ "$$(grep -oF '<downloadurl type="full" format="zip">$(DOWNLOAD_URL)</downloadurl>' "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) does not carry exactly one <downloadurl> for $(VERSION)"; \
		rm -f "$(UPDATE_ARTIFACT).part"; exit 1; \
	fi; \
	if [ "$$(grep -oF "<sha256>" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ] \
		|| [ "$$(grep -oF "<downloadurl" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) carries a second <sha256> or <downloadurl> this recipe did not write"; \
		rm -f "$(UPDATE_ARTIFACT).part"; exit 1; \
	fi; \
	if [ "$$(awk -F'[<>]' '/<version>/{print $$3; exit}' "$(UPDATE_ARTIFACT).part")" != "$(VERSION)" ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) declares a version other than $(VERSION)"; \
		rm -f "$(UPDATE_ARTIFACT).part"; exit 1; \
	fi; \
	mv "$(UPDATE_ARTIFACT).part" "$(UPDATE_ARTIFACT)"; \
	echo "Wrote $(UPDATE_ARTIFACT): $(DOWNLOAD_URL), sha256 $$SHA256"

clean:
	@rm -rf "$(BUILD_DIR)" "$(INSTALL_DIR)/release" "$(INSTALL_DIR)/dev"
