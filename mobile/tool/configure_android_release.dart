import 'dart:io';

const _marker = '# Moscow Pilgrim: WorkManager / Room R8 startup crash guard';

const _rules = '''
$_marker
# WorkManager stores its internal state in a Room database. In minified release
# builds Room creates the generated *_Impl class reflectively. Keep the database
# implementations and constructors intact so AndroidX Startup can initialize
# WorkManager before Flutter starts.
-keep class * extends androidx.room.RoomDatabase { *; }
-keep class androidx.work.impl.WorkDatabase_Impl { *; }

# WorkManager and AndroidX Startup are initialized from manifest metadata and use
# implementation classes that can be reached indirectly during app startup.
-keep class androidx.work.impl.** { *; }
-keep class androidx.startup.** { *; }
-keep class * implements androidx.startup.Initializer { *; }
''';

void main() {
  final appDirectory = Directory('android/app');
  if (!appDirectory.existsSync()) {
    stderr.writeln(
      'Android project not found. Run first:\n'
      '  flutter create . --platforms=android,ios --org ru.mospalomnik',
    );
    exitCode = 2;
    return;
  }

  final rulesFile = File('android/app/proguard-rules.pro');
  _ensureRules(rulesFile);

  final kotlinBuild = File('android/app/build.gradle.kts');
  final groovyBuild = File('android/app/build.gradle');

  if (kotlinBuild.existsSync()) {
    _ensureKotlinDsl(kotlinBuild);
  } else if (groovyBuild.existsSync()) {
    _ensureGroovyDsl(groovyBuild);
  } else {
    stderr.writeln(
      'Neither android/app/build.gradle.kts nor android/app/build.gradle exists.',
    );
    exitCode = 3;
    return;
  }

  stdout.writeln('Android release R8 guard is configured.');
}

void _ensureRules(File file) {
  final existing = file.existsSync() ? file.readAsStringSync() : '';
  if (existing.contains(_marker)) {
    stdout.writeln('ProGuard rules already contain the WorkManager guard.');
    return;
  }

  final separator = existing.isEmpty || existing.endsWith('\n') ? '' : '\n';
  file.writeAsStringSync('$existing$separator\n$_rules');
  stdout.writeln('Updated ${file.path}.');
}

void _ensureKotlinDsl(File file) {
  final source = file.readAsStringSync();
  if (source.contains('"proguard-rules.pro"')) {
    stdout.writeln('${file.path} already references proguard-rules.pro.');
    return;
  }

  final block = _findReleaseBlock(source, const [
    'release {',
    'getByName("release") {',
  ]);
  if (block == null) {
    _failToPatch(file.path);
    return;
  }

  final indent = _lineIndent(source, block.start) + '    ';
  final addition = '''\n$indent// Keep AndroidX WorkManager/Room usable after R8 shrinking.\n${indent}proguardFiles(\n$indent    getDefaultProguardFile("proguard-android-optimize.txt"),\n$indent    "proguard-rules.pro",\n$indent)\n''';
  final updated = source.replaceRange(block.end, block.end, addition);
  file.writeAsStringSync(updated);
  stdout.writeln('Updated ${file.path}.');
}

void _ensureGroovyDsl(File file) {
  final source = file.readAsStringSync();
  if (source.contains("'proguard-rules.pro'") ||
      source.contains('"proguard-rules.pro"')) {
    stdout.writeln('${file.path} already references proguard-rules.pro.');
    return;
  }

  final block = _findReleaseBlock(source, const ['release {']);
  if (block == null) {
    _failToPatch(file.path);
    return;
  }

  final indent = _lineIndent(source, block.start) + '    ';
  final addition = '''\n$indent// Keep AndroidX WorkManager/Room usable after R8 shrinking.\n${indent}proguardFiles getDefaultProguardFile('proguard-android-optimize.txt'), 'proguard-rules.pro'\n''';
  final updated = source.replaceRange(block.end, block.end, addition);
  file.writeAsStringSync(updated);
  stdout.writeln('Updated ${file.path}.');
}

_Block? _findReleaseBlock(String source, List<String> signatures) {
  for (final signature in signatures) {
    var searchFrom = 0;
    while (true) {
      final start = source.indexOf(signature, searchFrom);
      if (start < 0) break;

      final openingBrace = source.indexOf('{', start);
      if (openingBrace < 0) break;

      var depth = 0;
      for (var i = openingBrace; i < source.length; i++) {
        final char = source[i];
        if (char == '{') depth++;
        if (char == '}') {
          depth--;
          if (depth == 0) {
            return _Block(start: start, end: i);
          }
        }
      }

      searchFrom = start + signature.length;
    }
  }
  return null;
}

String _lineIndent(String source, int index) {
  final lineStart = source.lastIndexOf('\n', index);
  final from = lineStart < 0 ? 0 : lineStart + 1;
  final line = source.substring(from, index);
  return RegExp(r'^\s*').firstMatch(line)?.group(0) ?? '';
}

void _failToPatch(String path) {
  stderr.writeln(
    'Could not locate the release build block in $path. '
    'proguard-rules.pro was created, but Gradle was not modified.',
  );
  exitCode = 4;
}

class _Block {
  const _Block({required this.start, required this.end});

  final int start;
  final int end;
}
