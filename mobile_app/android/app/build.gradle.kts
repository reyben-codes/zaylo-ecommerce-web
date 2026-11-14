import java.security.KeyStore
import java.security.MessageDigest

plugins {
    id("com.android.application")
    // gradle.properties turns off AGP's built-in Kotlin (android.builtInKotlin=false), so the
    // Kotlin plugin is applied here; it provides the kotlin { compilerOptions } block below.
    id("org.jetbrains.kotlin.android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
    id("com.google.gms.google-services")
}

android {
    namespace = "com.example.zaylo_mobile"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    applicationVariants.all {
        val variant = this
        outputs.all {
            val output = this as com.android.build.gradle.internal.api.BaseVariantOutputImpl
            val projectName = "Zaylo Mobile"
            val buildType = variant.buildType.name
            output.outputFileName = "${projectName}-${buildType}.apk"
        }
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.example.zaylo_mobile"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    // The release workflow names the team's keystore in ZAYLO_KEYSTORE. Relying on the default debug key there made
    // the build machine sign each release with a new random key, which Google sign-in rejects and phones cannot
    // install as an update. Debug builds keep using this computer's debug key; releases require the team key.
    signingConfigs {
        System.getenv("ZAYLO_KEYSTORE")?.takeIf { it.isNotBlank() }?.let { path ->
            create("team") {
                storeFile = file(path)
                storePassword = "android"
                keyAlias = "androiddebugkey"
                keyPassword = "android"
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.findByName("team")
        }
    }
}

val verifyReleaseKey by tasks.registering {
    doLast {
        val path = System.getenv("ZAYLO_KEYSTORE")?.takeIf { it.isNotBlank() }
            ?: throw GradleException("Release builds require ZAYLO_KEYSTORE pointing to the existing team signing key. Debug-key fallback is disabled.")
        val keystore = KeyStore.getInstance(file(path), "android".toCharArray())
        val certificate = keystore.getCertificate("androiddebugkey")
            ?: throw GradleException("The configured keystore does not contain the team signing certificate.")
        val sha1 = MessageDigest.getInstance("SHA-1").digest(certificate.encoded)
            .joinToString(":") { "%02X".format(it.toInt() and 0xff) }
        if (sha1 != "76:70:DD:8F:BA:94:A9:5E:16:10:34:D3:11:69:A1:50:D3:B3:4B:37") {
            throw GradleException("Release signing certificate does not match the existing app. Refusing an incompatible update.")
        }
    }
}

tasks.matching { it.name == "preReleaseBuild" }.configureEach {
    dependsOn(verifyReleaseKey)
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
