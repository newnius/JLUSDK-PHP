<?php
/*
 * 
 *@author Newnius
 *@source code https://github.com/newnius/JLUSDK-PHP
 */
class JluScore
{
  private $cookie;
  private $userId;
  private $stuName;
  private $classNo;
  private $password;
  private $currentTermId;

  /*
   *
   *@param $encrypted: whether password has been encrypted (md5)
   */
  public function JluScore($classNo, $password, $encrypted = true)
  {
    $this->classNo = $classNo;
    $this->password = $password;
    if(!$encrypted)
      $this->password = md5('UIMS'.$this->classNo.$this->password);
  }

  /*
   *@description return currentTermId
   * if not set, request from database or cjcx.jlu.edu.cn
   */
  private function getCurrentTermId(){
    if(isset($this->currentTermId))
      return $this->currentTermId;
    if($this->loadCurrentTermIdFromDB() || $this->loadCurrentTermIdFromCjcx())
      return $this->currentTermId;
    return false;
  }

  /*
   * get user information, including:
   * 1. class No
   * 2. student name
   * 3. college
   * etc.
   *@hint not completed
   */
  public function getStuInfo()
  {
    if(!isset($this->cookie))
      $this->login();
    if(!isset($this->cookie))
      return null;
    $url = 'http://cjcx.jlu.edu.cn/score/action/getCurrentUserInfo.php'; 
    $opts = array(
      'http'=>array(
        'method'=>'GET',
        'header'=>'Cookie:'.$this->cookie.PHP_EOL.
          'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/44.0.2403.89 Chrome/44.0.2403.89 Safari/537.36'."\r\n".
          'Referer:Referer=http://cjcx.jlu.edu.cn/score/userLogin.php'
         )
    );
    $context = stream_context_create($opts);
    $html = file_get_contents($url, false, $context);
    $res = json_decode($html, true);
    return $res;
  }

  /*
   *@description return scores by termId, default currentTermId
   *@param termId: id of term, determined by uims system
   *@param filter_array: keys needed to be shown
   */
  public function getScore($termId = -1, $filter_array = array('kcmc', 'cj', 'gpoint'))
  {
    if(!isset($this->cookie))
      $this->login();
    if(!isset($this->cookie))
      return null;
    if($termId == -1)
      $termId = $this->getCurrentTermId();
    if(!$termId)
      return null;
    $url = 'http://cjcx.jlu.edu.cn/score/action/service_res.php';
    $data = '{"tag":"lessonSelectResult@oldStudScore","params":{"xh":"'.$this->username.'", "termId":'.$termId.'}}';
    $opts = array(
      'http'=>array(
        'method'=>'POST',
        'header'=>'Content-Type:application/json;charset=UTF-8'.PHP_EOL.
          'Content-length:'.strlen($data).PHP_EOL.
          'Cookie:'.$this->cookie.PHP_EOL.
          'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/44.0.2403.89 Chrome/44.0.2403.89 Safari/537.36'."\r\n".
          'Referer:Referer=http://cjcx.jlu.edu.cn/score/userLogin.php'."",
        'content'=> $data )
    );
    $context = stream_context_create($opts);
    $html = file_get_contents($url, false, $context);
    return $this->formatScore(json_decode($html, true), $filter_array);
  }

  /*
   *@description load currentTermId from cjcx.jlu.edu.cn
   */
  private function loadCurrentTermIdFromCjcx(){
    if(!isset($this->cookie))
      $this->login();
    if(!isset($this->cookie))
      return false;
    $url = 'http://cjcx.jlu.edu.cn/score/action/getCurrentUserInfo.php'; 
    $opts = array(
      'http'=>array(
        'method'=>'GET',
        'header'=>'Cookie:'.$this->cookie.PHP_EOL.
          'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/44.0.2403.89 Chrome/44.0.2403.89 Safari/537.36'."\r\n".
          'Referer:Referer=http://cjcx.jlu.edu.cn/score/userLogin.php'
         )
    );
    $context = stream_context_create($opts);
    $html = file_get_contents($url, false, $context);
    $arr = json_decode($html, true);
    if(isset($arr['defRes']['teachingTerm'])){
      $this->currentTermId = $arr['defRes']['teachingTerm'];
      return true;
    }
    return false;
  }

  /*
   *@description load currentTermId from database
   * complete it yourself
   */
  private function loadCurrentTermIdFromDB(){
    return false;
  }

  /*
   *@description do login, get cookie
   */
  private function login()
  {
    $url = 'http://cjcx.jlu.edu.cn/score/action/security_check.php';
    $j_username = $this->classNo;
    $j_password = $this->password;
    $data = array('j_username' => $j_username, 'j_password'=>$j_password);
    $data = http_build_query($data);
    $opts = array(
      'http'=>array(
        'method'=>'POST',
        'follow_location' => false,
        'header'=>'Content-Type:application/x-www-form-urlencoded; charset=UTF-8'.PHP_EOL.
          'Content-length:'.strlen($data).PHP_EOL.
          'User-Agent:Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/44.0.2403.89 Chrome/44.0.2403.89 Safari/537.36'."\r\n".
          'Referer:Referer=http://cjcx.jlu.edu.cn/score/userLogin.php'."",
        'content'=> $data )
    );
    $context = stream_context_create($opts);
    $html = file_get_contents($url, false, $context);
    $header = $this->parseHeaders($http_response_header);
    if(isset($header['Location']) && strpos($header['Location'], 'index.php'))
    {
      $this->cookie = $header['Set-Cookie'];
    }
	}

  /*
   *
   * convert complex array to specified user-friendly array
   */
  private function formatScore($score_array, $filter_array)
  {
    if(!isset($score_array['errno']) || $score_array['errno'] != 0)
      return null;
    $scores = array();
    $cnt = 0;
    foreach($score_array['items'] as $course)
    {
      foreach($course as $key=>$value)
        if(in_array($key, $filter_array))
          $scores[$cnt][$key] = $value;
      $cnt++;
    }
    return $scores;
  }
 
  /*
   *@description convert $i=>$value array to $key=>$value array
   *
   */
  private function parseHeaders( $headers )
  {
    $head = array();
    foreach( $headers as $k=>$v )
    {
      $t = explode( ':', $v, 2 );
      if( isset( $t[1] ) )
        $head[ trim($t[0]) ] = trim( $t[1] );
      else
      {
        $head[] = $v;
        if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
          $head['reponse_code'] = intval($out[1]);
      }
    }
    return $head;
  }
}
?>
<?php
// test code
//$jluScore = (new JluScore('10010101', '123456', false));
//var_dump($jluScore->getStuInfo());
//var_dump($jluScore->getScore(-1, array('kcmc', 'cj')));
?>
