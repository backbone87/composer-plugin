<?php

/**
 * Contao Composer Installer
 *
 * Copyright (C) 2013 Contao Community Alliance
 *
 * @package contao-composer
 * @author  Dominik Zogg <dominik.zogg@gmail.com>
 * @author  Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author  Tristan Lins <tristan.lins@bit3.de>
 * @link    http://c-c-a.org
 * @license LGPL-3.0+
 */

namespace ContaoCommunityAlliance\Composer\Plugin;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Composer\Package\PackageInterface;

/**
 * Module installer that use copies to install the extensions into the contao file hierarchy.
 */
class CopyInstaller extends AbstractInstaller
{
	protected function updateSources($map, PackageInterface $package)
	{
		$deleteCount = 0;
		$copyCount   = 0;

		$root        = $this->plugin->getContaoRoot($this->composer->getPackage());
		$installPath = $this->getInstallPath($package);
		$sources     = $this->getSourcesSpec($package);

		// remove symlinks
		$this->removeAllSymlinks($map, $root, $deleteCount);

		// update copies
		$copies = $this->updateAllCopies($sources, $root, $installPath, $copyCount);

		// remove obsolete copies
		$this->removeObsoleteCopies($map, $copies, $root, $deleteCount);

		if ($deleteCount && !$this->io->isVerbose()) {
			$this->io->write(
				sprintf(
					'  - removed <info>%d</info> files',
					$deleteCount
				)
			);
		}

		if ($copyCount && !$this->io->isVerbose()) {
			$this->io->write(
				sprintf(
					'  - installed <info>%d</info> files',
					$copyCount
				)
			);
		}
	}

	protected function removeAllSymlinks($map, $root, &$deleteCount)
	{
		foreach (array_values($map['links']) as $link) {
			if ($this->io->isVerbose()) {
				$this->io->write(
					sprintf(
						'  - rm link <info>%s</info>',
						$link
					)
				);
			}

			$this->filesystem->remove($root . DIRECTORY_SEPARATOR . $link);
			$deleteCount++;
		}
	}

	protected function updateAllCopies($sources, $root, $installPath, &$copyCount)
	{
		$copies = array();
		foreach ($sources as $source => $target) {
			if (is_dir($installPath . DIRECTORY_SEPARATOR . $source)) {
				$files    = array();
				$iterator = new \RecursiveDirectoryIterator(
					$installPath . DIRECTORY_SEPARATOR . $source,
					\FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
				);
				$iterator = new \RecursiveIteratorIterator(
					$iterator
				);
				foreach ($iterator as $sourceFile) {
					$unPrefixedPath     = self::unprefixPath(
						$installPath . DIRECTORY_SEPARATOR . ($source ? $source . DIRECTORY_SEPARATOR : ''),
						$sourceFile->getRealPath()
					);
					$targetPath         = $target . DIRECTORY_SEPARATOR . $unPrefixedPath;
					$files[$targetPath] = $sourceFile;
				}
			}
			else {
				$files = array($target => new \SplFileInfo($installPath . DIRECTORY_SEPARATOR . $source));
			}

			/** @var \SplFileInfo $sourceFile */
			foreach ($files as $targetPath => $sourceFile) {
				if ($this->io->isVerbose()) {
					$this->io->write(
						sprintf(
							'  - cp <info>%s</info>',
							$targetPath
						)
					);
				}

				$this->filesystem->ensureDirectoryExists(dirname($root . DIRECTORY_SEPARATOR . $targetPath));
				copy($sourceFile->getRealPath(), $root . DIRECTORY_SEPARATOR . $targetPath);
				$copyCount++;
				$copies[] = $targetPath;
			}
		}

		return $copies;
	}

	protected function removeObsoleteCopies($map, $copies, $root, &$deleteCount)
	{
		$obsoleteCopies = array_diff($map['copies'], $copies);
		foreach ($obsoleteCopies as $obsoleteCopy) {
			if ($this->io->isVerbose()) {
				$this->io->write(
					sprintf(
						'  - rm obsolete <info>%s</info>',
						$obsoleteCopy
					)
				);
			}
			$this->filesystem->remove($root . DIRECTORY_SEPARATOR . $obsoleteCopy);
			$deleteCount++;
		}
	}
}
