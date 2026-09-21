/*
 * Зеркала вместо канонических хостов: сети, из которых собирается этот проект,
 * фильтруют часть адресов Google и Maven Central.
 *
 * - repo1.maven.org бывает недоступен, поэтому первым идёт зеркало Maven Central
 *   на Google Cloud Storage;
 * - google() по умолчанию ходит на dl.google.com, который в некоторых сетях
 *   молча отваливается по таймауту, тогда как maven.google.com с теми же
 *   артефактами отвечает нормально. Поэтому он объявлен явно и первым.
 *
 * Артефакты на зеркалах идентичные, а Gradle переходит к следующему репозиторию,
 * когда до текущего не достучаться.
 */
val mavenCentralMirror = "https://maven-central.storage-download.googleapis.com/maven2"
val googleMirror = "https://maven.google.com"

/*
 * Внутри pluginManagement адреса продублированы литералами намеренно: этот блок
 * Gradle вычисляет раньше тела скрипта, и объявленные выше val ему не видны.
 */
pluginManagement {
    repositories {
        maven("https://maven.google.com") {
            content {
                includeGroupByRegex("com\\.android.*")
                includeGroupByRegex("com\\.google.*")
                includeGroupByRegex("androidx.*")
            }
        }
        google {
            content {
                includeGroupByRegex("com\\.android.*")
                includeGroupByRegex("com\\.google.*")
                includeGroupByRegex("androidx.*")
            }
        }
        maven("https://maven-central.storage-download.googleapis.com/maven2")
        mavenCentral()
        gradlePluginPortal()
    }
}

dependencyResolutionManagement {
    repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
    repositories {
        maven(googleMirror)
        google()
        maven(mavenCentralMirror)
        mavenCentral()
    }
}

rootProject.name = "OTP Gateway Client"
include(":app")
