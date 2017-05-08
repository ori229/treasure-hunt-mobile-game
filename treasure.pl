#!/path/to/bin/perl
# https://.../treasure.pl
# ?page=7013432&team=boys
# ?page=7013432&answer=hill&attempt=2&team=boys
# ?page=7013432&reveal=true&team=boys
# For Admin:
# ?admin=true&game=jer_heb
# ?game=jer_heb&team=Team1&showMap=true
use strict;
use CGI;
use CGI::Carp qw(fatalsToBrowser);
use Encode; #use utf8;
use Time::HiRes;
$| = 1; # unbuffered output
my $q=new CGI;
print $q->header(-charset=>'utf-8');
(my $SEC, my $MIN, my $HOUR, my $DAY,my $MONTH,my $YEAR) =
             (localtime)[0,1,2,3,4,5]; $MONTH+=1; $YEAR+=1900;
my $now = sprintf("%04d%02d%02d_%02d%02d%02d",$YEAR,$MONTH,$DAY,$HOUR,$MIN,$SEC);
#print $now;
my $path = '/exlibris/aleph/a22_1/aleph/cgi/treasure/';
my $game = $q->param('game');
my $team = $q->param('team');

# load conf:
my $conf_file = $path.'data/'.$game.'.conf';
my $conf_text = slurp($conf_file);
#print $conf_file , $conf_text;
my %conf;
while ($conf_text=~m/(.*?):\n(.*?)\n/gs) {
  #print $1,'=',$2,"\n";
  $conf{$1} = $2;
}

##### first page
if (!$team) {
  my $out_html = translate_html(slurp($path . 'html/first.htm'));
  $out_html=~s/{game}/$game/g;
  $out_html=~s/{game}/$game/g;
  output_html($out_html);
  exit;
}

##### admin page
if ($team eq 'admin') {
  foreach my $file ( glob( $path . 'data/team*' ) ) {
    print "$file <br/>\n <pre>";
    print slurp($file),"</pre>";
    print "___________________<br/><br/><br/><br/>\n";
  }
  exit;
}

my $team_file = $path.'data/team_'.$game.'_'.$team.'.data';
if ($q->param('showMap')) {  showMap($team_file); }

open( OUT_F, ">>$team_file") or die "Cannot open $team_file as output\n" ;
my $team_text = '';
my $current_points = 0;
my $start_time;
my $elapsed_time = 0;
  $team_text = slurp($team_file);
  if ($team_text!~/start timestamp/) {
    my $start = time();
    print OUT_F "start timestamp:$start\ntime:$now\n";
  }
  if ($team_text=~/.*points:(\d*)/s) {
    $current_points = $1;
  }
  if ($team_text=~/start timestamp:(\d*)/s) {
    $start_time = $1;
    $elapsed_time = wdhms(time() - $start_time);
  } else {
    $elapsed_time = wdhms(1); # for the first page: 1 sec
  }

my $page_param = $q->param('page');
my $page_no = substr($page_param,1,2);
my $the_question = $conf{$page_no.'_q'} ; #print "ooo  $page_no $the_question";

my $answer = $q->param('answer');
my $attempt = $q->param('attempt');
if (!$attempt) { $attempt = 1 };
##### question page:
if ($page_param && !$answer) {
  my $out_html = translate_html(slurp($path.'html/question.htm'));
  $out_html=~s/{page}/$page_param/g;
  $out_html=~s/{attempt}/$attempt/g;
  if ($conf{$page_no.'_q_img'}) {
    $the_question.='<br/><img style="width:100%; max-width:800px" src="' . $conf{$page_no.'_q_img'} . '"./>';
  }
  $out_html=~s/{question}/$the_question/g;
  output_html($out_html);
  print OUT_F "page: $page_no attempt:$attempt\n";
  exit;
}

##### post an answer:
# ?page=7013432&answer=hill&attempt=2&team=boys
if ($page_param && $answer) {

  # log geo location
  if ( $q->param('lat')  ){
    print OUT_F "GEO: lat " . $q->param('lat') . " lon " . $q->param('lon') . "\n";
  }

  my $correct_answer = $conf{$page_no.'_a'} ; print "<!-- ooo  $page_no $correct_answer -->";
  my $added_points = 12 - (2 * $attempt);
  if ($answer=~/$correct_answer/i) {
    $current_points += $added_points;
    my $out_html = translate_html(slurp($path.'html/correct.htm'));
    my $next_page = hidden_page_num($page_no+1);
    $out_html=~s/{page}/$next_page/g;
    $out_html=~s/{added_points}/$added_points/g;
    my $after_reply = $conf{$page_no.'_after_reply'};
    $out_html=~s/{after_reply}/$after_reply/g;
    output_html($out_html);
    print OUT_F "CORRECT page: $page_no attempt:$attempt correct_answer:$correct_answer . answer:$answer elapsed:$elapsed_time\n";
    print OUT_F "points:$current_points\n";
    exit;
  } else {
    print OUT_F "WRONG page: $page_no attempt:$attempt correct_answer:$correct_answer . answer:$answer elapsed:$elapsed_time\n";

    $attempt++;
    if ($attempt > 3) {
      ##### after too many wrong asnwers - show the answer
      my $out_html = translate_html(slurp($path.'html/after_many_mistakes.htm'));
      my $next_page = hidden_page_num($page_no+1);
      $out_html=~s/{page}/$next_page/g;
      my $after_reply = $conf{$page_no.'_after_reply'};
      $out_html=~s/{after_reply}/$after_reply/g;
      $out_html=~s/{answer}/$correct_answer/g;
      output_html($out_html);
      print OUT_F "MANY_WRONG page: $page_no attempt:$attempt correct_answer:$correct_answer . answer:$answer elapsed:$elapsed_time\n";
      print OUT_F "points:$current_points\n";
      exit;
    }

    my $out_html = translate_html(slurp($path.'html/wrong.htm'));
    $out_html=~s/{page}/$page_param/g;
    $out_html=~s/{attempt}/$attempt/g;
    if ($conf{$page_no.'_q_img'}) {
      $the_question.='<br/><img style="width:100%; max-width:800px;" src="' . $conf{$page_no.'_q_img'} . '"./>';
    }
    $out_html=~s/{question}/$the_question/g;
    output_html($out_html);
    exit;
  }
}

my $log_file = $path."logs/main.log";
open( OUT_F, ">>$log_file") or die "Cannot open $log_file as output\n" ;
print OUT_F "ooo $now\n\n";

print "error...\n";

exit;
###############################################################################
my $REMOTE_ADDR = $ENV{REMOTE_ADDR}; print " $REMOTE_ADDR \n";
print "<br><br>\n";
foreach my $key (sort keys(%ENV) ) { print "$key = $ENV{$key}<br>\n"; }
my @names = $q->param; my $c = 0;
foreach my $name (sort @names) { $c++; print "<br> $name ->", $q->param($name),"\n"; }
print "<br><br>\n";


###############################################################################
sub translate_html {
    my $html = shift;
    foreach my $key (keys %conf) {
        $html=~s/{$key}/$conf{$key}/g;
    }
    $html=~s/{game}/$game/g;
    $html=~s/{team}/$team/g;
    $html=~s/{current_points}/$current_points/g;
    $html=~s/{elapsed_time}/$elapsed_time/g;
    return $html;
}

###############################################################################
sub output_html{
    my $html = shift;
    print $html;
}

###############################################################################
sub hidden_page_num{
    my $num = shift;
    return '7' . sprintf("%02d", $num) . '3432';
}

###############################################################################
sub slurp {
    my $file = shift;
    open my $fh, '<', $file or die "can not find $file";
    local $/ = undef;
    my $cont = <$fh>;
    close $fh;
    return $cont;
}

###############################################################################
# from http://www.perlmonks.org/?node_id=110550
sub wdhms {
    my( $weeks, $days, $hours, $minutes, $seconds, $sign, $res ) = qw/+0 0 0 0 0/;

    $seconds = shift;
    $sign    = $seconds == abs $seconds ? '' : '-';
    $seconds = abs $seconds;

    ($seconds, $minutes) = ($seconds % 60, int($seconds / 60)) if $seconds;
    ($minutes, $hours  ) = ($minutes % 60, int($minutes / 60)) if $minutes;
    ($hours,   $days   ) = ($hours   % 24, int($hours   / 24)) if $hours;
    ($days,    $weeks  ) = ($days    %  7, int($days    /  7)) if $days;

    $res = sprintf '%02d',     $seconds;
    $res = sprintf "%02d:$res", $minutes if $minutes or $hours or $days or $weeks;
    $res = sprintf "%02d:$res", $hours   if             $hours or $days or $weeks;
    if ($days > 0) {
      $res = sprintf "%dd $res", $days    if                       $days or $weeks;
      $res = sprintf "%dw$res", $weeks   if                                $weeks;
    }

    return "$sign$res";
}

sub showMap {
    my $file = shift;
    $team_text = slurp($team_file);
    # GEO: lat 31.7493453 lon 35.1863789
    my $values = '';
    while ($team_text=~m/GEO: lat (.*?) lon (.*?)\n/gs) {
        $values .= ' ['.$1.', '.$2.', ""],'
    }
    my $html_text = slurp($path . 'html/showMap.htm');
    $html_text=~s/{values}/$values/;
    print $html_text;
    exit;
}
