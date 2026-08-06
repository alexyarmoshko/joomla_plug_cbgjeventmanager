# Reproducible packaging for plug_cbgjeventmanager: the ZIP bytes depend only on the
# packaged files and one timestamp, never on the machine that built it, so a release
# can be rebuilt from its tag and still hash to the sha256 its update descriptor
# claims. The workflow and its rationale are in README.md, section "Build".
#
# Two things differ from a stock Joomla extension, both forced by the CB manifest:
#
#   1. A <cbinstall> manifest spends <version> on the Community Builder compatibility
#      version and <release> on the extension's own semver. The release version - the
#      tag, the ZIP name, the download URL, the descriptor - is therefore <release>.
#      Reading <version> here would package 0.1.0 under the name 2.11.0.
#   2. CB installs a plugin from the contents of its plugin directory, so the archive
#      root is $(PLUGIN_DIR), not the repository root. PACKAGE_FILES is relative to
#      that directory and the packager is handed it as the archive root.

SHELL := /bin/sh

EXT_NAME := plug_cbgjeventmanager
PLUGIN_DIR := components/com_comprofiler/plugin/user/plug_cbgjeventmanager
MANIFEST := $(PLUGIN_DIR)/cbgjeventmanager.xml
RELEASE_NOTES := docs/RELEASE.md
GITHUB_OWNER := alexyarmoshko
GITHUB_REPO := joomla_plug_cbgjeventmanager

# One explicit list of what ships. Never a directory. Paths are relative to
# $(PLUGIN_DIR) because that is the archive root - see note 2 above.
#
# LICENSE is deliberately absent: CB installs the files its manifest <files> block
# declares, and shipping one it does not declare puts a file in the package that
# never lands anywhere. The licence is carried by the repository, not the package.
PACKAGE_FILES := \
	cbgjeventmanager.xml \
	cbgjeventmanager.php \
	component.cbgjeventmanager.php \
	index.html \
	language/index.html \
	language/default_language/index.html \
	language/default_language/admin_language.php \
	language/default_language/language.php \
	library/index.html \
	library/CBGJEventManager.php \
	library/Trigger/index.html \
	library/Trigger/EventsTrigger.php \
	media/index.html \
	media/js/index.html \
	media/js/owner-picker.js

JZIP ?= tools/jzip.php
ZIP_LEVEL ?= 9
INSTALL_DIR ?= installation
BUILD_DIR ?= build

UPDATE_TEMPLATE := $(EXT_NAME).update.xml
SHA256_PLACEHOLDER := 0000000000000000000000000000000000000000000000000000000000000000

# <release> is the extension version; <version> is the CB compatibility version.
# `override` is load-bearing. The tag, the ZIP name and the download URL all derive
# from this, and deriving it from the manifest instead of typing it is the whole point
# of `release`. Without `override`, `make release VERSION=x` tags a version the manifest
# never declared - and refusing to package it afterwards does not remove the tag.
override VERSION := $(shell awk -F'[<>]' '/<release>/{print $$3; exit}' $(MANIFEST))
CB_COMPAT := $(shell awk -F'[<>]' '/<version>/{print $$3; exit}' $(MANIFEST))
ZIP_NAME := $(EXT_NAME)-v$(subst .,-,$(VERSION)).zip
RELEASE_STAGE := $(BUILD_DIR)/release
DEV_STAGE := $(BUILD_DIR)/dev
RELEASE_ROOT := $(RELEASE_STAGE)/$(PLUGIN_DIR)
RELEASE_ZIP := $(INSTALL_DIR)/release/$(ZIP_NAME)
DEV_ZIP := $(INSTALL_DIR)/dev/$(ZIP_NAME)
UPDATE_ARTIFACT := $(INSTALL_DIR)/release/$(UPDATE_TEMPLATE)
DOWNLOAD_URL := https://github.com/$(GITHUB_OWNER)/$(GITHUB_REPO)/releases/download/$(VERSION)/$(ZIP_NAME)

# No Composer here: this is a CB plugin with no dependencies and no automated
# harness. These are the repository's own gates, and `release` runs them before it
# tags - so they are `:=`, not `?=`: an environment variable of the same name must
# not be able to replace them silently. That also means they must be defined AFTER
# the variables they reference, since `:=` expands immediately.
DEPS_CMD ?= @echo "No dependencies to install."
TEST_CMD := echo "No automated test harness in this repository - release verification is manual."
# $(JZIP) is linted although it does not ship: it is the packager, and a syntax
# error in it would otherwise surface only after `release` has created the tag.
LINT_CMD := set -e; \
	for f in $(filter %.php,$(addprefix $(PLUGIN_DIR)/,$(PACKAGE_FILES))) $(JZIP); do php -l "$$f" >/dev/null || exit 1; done; \
	for f in $(MANIFEST) $(UPDATE_TEMPLATE); do \
		php -r 'libxml_use_internal_errors(true); if (simplexml_load_file($$argv[1]) === false) { fwrite(STDERR, "FAIL: malformed XML in $$argv[1]\n"); exit(1); }' "$$f" || exit 1; \
	done; \
	node --check $(PLUGIN_DIR)/media/js/owner-picker.js; \
	echo "lint: ok"

sha256 = $$( { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$(1)"; else shasum -a 256 "$(1)"; fi; } | awk '{print $$1}' | grep -Ex '[0-9a-f]{64}' )

# Package archive root $(2) into $(1), stamping every entry with epoch $(3).
define package
	@set -e; \
	for f in $(PACKAGE_FILES); do \
		if [ ! -f "$(2)/$$f" ]; then \
			echo "FAIL: $$f is in PACKAGE_FILES but not in $(2)/ - the staged tree is incomplete"; \
			exit 1; \
		fi; \
	done; \
	mkdir -p "$(dir $(1))"; \
	archive="$$(cd "$(dir $(1))" && pwd)/$$(basename "$(1)").part"; stamp="$(3)"; \
	rm -f "$(1).part"; \
	if [ -f "$(JZIP)" ]; then \
		php "$(JZIP)" --level=$(ZIP_LEVEL) "$$archive" "$$stamp" "$(2)" $(PACKAGE_FILES) >/dev/null; \
		how="jzip level $(ZIP_LEVEL)"; \
	else \
		echo "WARNING: jzip not found at $(JZIP) - falling back to zip." >&2; \
		echo "WARNING: the package is NOT reproducible - zip stores this machine's mtimes and host metadata." >&2; \
		( cd "$(2)" && zip -q -X -$(ZIP_LEVEL) "$$archive" $(PACKAGE_FILES) ); \
		how="zip fallback"; \
	fi; \
	mv "$(1).part" "$(1)"; \
	sum="$(call sha256,$(1))"; \
	echo "$(1): $(words $(PACKAGE_FILES)) entries, $$(wc -c < "$(1)") bytes, $$how, sha256 $$sum"
endef

.PHONY: info deps test lint release dist_release dist_dev update_manifest clean

info:
	@echo "Extension:       $(EXT_NAME)"
	@echo "Version:         $(VERSION)  (<release>)"
	@echo "CB compat:       $(CB_COMPAT)  (<version>)"
	@echo "Archive root:    $(PLUGIN_DIR)"
	@echo "Source:          $(PACKAGE_FILES)"
	@echo "Release package: $(RELEASE_ZIP)"
	@echo "Dev package:     $(DEV_ZIP)"
	@echo "Update template: $(UPDATE_TEMPLATE)"
	@echo "Update artifact: $(UPDATE_ARTIFACT)"
	@echo "Download URL:    $(DOWNLOAD_URL)"
	@echo "Packager:        $(JZIP) (level $(ZIP_LEVEL))"

deps:
	$(DEPS_CMD)

test:
	@$(TEST_CMD)

lint:
	@$(LINT_CMD)

release:
	@set -e; \
	dirty="$$(git status --porcelain)" || { echo "FAIL: git status failed - cannot confirm the tree is clean"; exit 1; }; \
	if [ -n "$$dirty" ]; then \
		echo "FAIL: working tree is dirty - commit or stash before tagging"; \
		printf '%s\n' "$$dirty"; \
		exit 1; \
	fi
	@if git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: tag $(VERSION) already exists - bump <release> in $(MANIFEST)"; \
		exit 1; \
	fi
	@if ! awk -v v="$(VERSION)" '/^## /{ h=$$2; if (h==v) found=1 } END{ exit !found }' "$(RELEASE_NOTES)"; then \
		echo "FAIL: $(RELEASE_NOTES) has no '## $(VERSION)' heading - write the release notes before tagging"; \
		exit 1; \
	fi
	$(MAKE) test
	$(MAKE) lint
	git tag "$(VERSION)"
	@echo "Tagged $(VERSION) - next: make dist_release"

dist_release:
	@if ! git rev-parse -q --verify "refs/tags/$(VERSION)" >/dev/null; then \
		echo "FAIL: no tag $(VERSION) - run 'make release' first"; \
		exit 1; \
	fi
	@# Only the payload files come from the tag. PACKAGE_FILES, the packager, the
	@# compression level and the descriptor template are all read from the checkout,
	@# so the archive is reproducible from the tag only when the checkout IS the tag.
	@set -e; \
	dirty="$$(git status --porcelain)" || { echo "FAIL: git status failed - cannot confirm the tree is clean"; exit 1; }; \
	if [ -n "$$dirty" ]; then \
		echo "FAIL: working tree is dirty - a release must be built from a clean checkout of $(VERSION)"; \
		printf '%s\n' "$$dirty"; \
		exit 1; \
	fi; \
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
	@# Same reasoning for the variables that decide the published bytes.
	@if [ "$(origin ZIP_LEVEL)" = "command line" ] || [ "$(origin JZIP)" = "command line" ] \
		|| [ "$(origin PACKAGE_FILES)" = "command line" ]; then \
		echo "FAIL: ZIP_LEVEL, JZIP and PACKAGE_FILES decide the published bytes and must come from"; \
		echo "      the tagged Makefile, not the command line. Change them in the repository instead."; \
		exit 1; \
	fi
	@# The macro below degrades to `zip` when the packager is missing. That is
	@# tolerable for a dev package and not for a published one: it would produce a
	@# non-reproducible archive and then stamp a descriptor claiming its checksum.
	@if [ ! -f "$(JZIP)" ]; then \
		echo "FAIL: $(JZIP) is missing - a release package must come from the deterministic"; \
		echo "      packager, never the zip fallback. Restore it before building."; \
		exit 1; \
	fi
	@rm -rf "$(RELEASE_STAGE)"
	@mkdir -p "$(RELEASE_STAGE)"
	@git -c core.autocrlf=false archive "$(VERSION)" | tar -x -C "$(RELEASE_STAGE)"
	@set -e; \
	exported="$$(awk -F'[<>]' '/<release>/{print $$3; exit}' "$(RELEASE_STAGE)/$(MANIFEST)")"; \
	if [ "$$exported" != "$(VERSION)" ]; then \
		echo "FAIL: tag $(VERSION) exports a manifest declaring $$exported"; \
		echo "      the ZIP name, download URL and checksum all come from the WORKING TREE manifest,"; \
		echo "      so packaging this would publish one version under another's name - move the tag"; \
		echo "      or fix <release> before rebuilding."; \
		exit 1; \
	fi
	$(call package,$(RELEASE_ZIP),$(RELEASE_ROOT),$$(git log -1 --format=%ct "$(VERSION)"))
	@rm -rf "$(RELEASE_STAGE)"
	$(MAKE) update_manifest

dist_dev:
	@rm -rf "$(DEV_STAGE)"
	@mkdir -p "$(DEV_STAGE)"
	@set -e; \
	for f in $(PACKAGE_FILES); do \
		mkdir -p "$(DEV_STAGE)/$$(dirname "$$f")"; \
		cp "$(PLUGIN_DIR)/$$f" "$(DEV_STAGE)/$$f"; \
	done
	@# A <cbinstall> manifest carries no <updateservers> today, so this strip is a
	@# no-op - kept so that adding one later cannot silently point a test install at
	@# the published descriptor and offer to "update" over itself.
	@sed '/<updateservers>/,/<\/updateservers>/d' "$(DEV_STAGE)/$(notdir $(MANIFEST))" > "$(DEV_STAGE)/manifest.tmp" \
		&& mv "$(DEV_STAGE)/manifest.tmp" "$(DEV_STAGE)/$(notdir $(MANIFEST))"
	@# grep exits 2 on an unreadable file, which an `if` cannot tell from "no match" -
	@# an assertion that silently passes when it could not check is worse than none.
	@set -e; \
	rc=0; grep -q "updateservers" "$(DEV_STAGE)/$(notdir $(MANIFEST))" || rc=$$?; \
	if [ "$$rc" -eq 0 ]; then \
		echo "FAIL: <updateservers> survived in the dev manifest"; \
		exit 1; \
	elif [ "$$rc" -gt 1 ]; then \
		echo "FAIL: cannot read $(DEV_STAGE)/$(notdir $(MANIFEST)) to confirm <updateservers> was stripped"; \
		exit 1; \
	fi
	$(call package,$(DEV_ZIP),$(DEV_STAGE),$$(git log -1 --format=%ct))
	@rm -rf "$(DEV_STAGE)"

update_manifest:
	@set -e; \
	if [ "$$(grep -oF "<sha256>$(SHA256_PLACEHOLDER)</sha256>" "$(UPDATE_TEMPLATE)" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_TEMPLATE) is not a template - <sha256> must be the $(SHA256_PLACEHOLDER) placeholder"; \
		exit 1; \
	fi; \
	SHA256="$(call sha256,$(RELEASE_ZIP))"; \
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
		exit 1; \
	fi; \
	if [ "$$(grep -oF '<downloadurl type="full" format="zip">$(DOWNLOAD_URL)</downloadurl>' "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) does not carry exactly one <downloadurl> for $(VERSION)"; \
		exit 1; \
	fi; \
	if [ "$$(grep -oF "<sha256>" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ] \
		|| [ "$$(grep -oF "<downloadurl" "$(UPDATE_ARTIFACT).part" | wc -l)" -ne 1 ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) carries a second <sha256> or <downloadurl> this recipe did not write"; \
		exit 1; \
	fi; \
	if [ "$$(awk -F'[<>]' '/<version>/{print $$3; exit}' "$(UPDATE_ARTIFACT).part")" != "$(VERSION)" ]; then \
		echo "FAIL: $(UPDATE_ARTIFACT) declares a version other than $(VERSION)"; \
		exit 1; \
	fi; \
	mv "$(UPDATE_ARTIFACT).part" "$(UPDATE_ARTIFACT)"
	@echo "Wrote $(UPDATE_ARTIFACT)"

clean:
	@rm -rf "$(BUILD_DIR)" "$(INSTALL_DIR)/release" "$(INSTALL_DIR)/dev"
