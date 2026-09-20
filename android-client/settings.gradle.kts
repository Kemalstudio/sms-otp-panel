/*
 * repo1.maven.org is not reachable from every network this project gets built
 * on, so Google's Cloud Storage mirror of Maven Central is listed first and the
 * canonical host is kept as a fallback. Both serve identical artifacts, and
 * Gradle moves on to the next repository when one cannot be reached.
 */
val mavenCentralMirror = "https://maven-central.storage-download.googleapis.com/maven2"

pluginManagement {
    repositories {
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
        google()
        maven(mavenCentralMirror)
        mavenCentral()
    }
}

rootProject.name = "OTP Gateway Client"
include(":app")
