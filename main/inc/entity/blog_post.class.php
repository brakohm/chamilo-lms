<?php

namespace Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 *
 * @license see /license.txt
 * @author autogenerated
 */
class BlogPost extends \CourseEntity
{
    /**
     * @return \Entity\Repository\BlogPostRepository
     */
     public static function repository(){
        return \Entity\Repository\BlogPostRepository::instance();
    }

    /**
     * @return \Entity\BlogPost
     */
     public static function create(){
        return new self();
    }

    /**
     * @var integer $c_id
     */
    protected $c_id;

    /**
     * @var integer $post_id
     */
    protected $post_id;

    /**
     * @var string $title
     */
    protected $title;

    /**
     * @var text $full_text
     */
    protected $full_text;

    /**
     * @var datetime $date_creation
     */
    protected $date_creation;

    /**
     * @var integer $blog_id
     */
    protected $blog_id;

    /**
     * @var integer $author_id
     */
    protected $author_id;


    /**
     * Set c_id
     *
     * @param integer $value
     * @return BlogPost
     */
    public function set_c_id($value)
    {
        $this->c_id = $value;
        return $this;
    }

    /**
     * Get c_id
     *
     * @return integer 
     */
    public function get_c_id()
    {
        return $this->c_id;
    }

    /**
     * Set post_id
     *
     * @param integer $value
     * @return BlogPost
     */
    public function set_post_id($value)
    {
        $this->post_id = $value;
        return $this;
    }

    /**
     * Get post_id
     *
     * @return integer 
     */
    public function get_post_id()
    {
        return $this->post_id;
    }

    /**
     * Set title
     *
     * @param string $value
     * @return BlogPost
     */
    public function set_title($value)
    {
        $this->title = $value;
        return $this;
    }

    /**
     * Get title
     *
     * @return string 
     */
    public function get_title()
    {
        return $this->title;
    }

    /**
     * Set full_text
     *
     * @param text $value
     * @return BlogPost
     */
    public function set_full_text($value)
    {
        $this->full_text = $value;
        return $this;
    }

    /**
     * Get full_text
     *
     * @return text 
     */
    public function get_full_text()
    {
        return $this->full_text;
    }

    /**
     * Set date_creation
     *
     * @param datetime $value
     * @return BlogPost
     */
    public function set_date_creation($value)
    {
        $this->date_creation = $value;
        return $this;
    }

    /**
     * Get date_creation
     *
     * @return datetime 
     */
    public function get_date_creation()
    {
        return $this->date_creation;
    }

    /**
     * Set blog_id
     *
     * @param integer $value
     * @return BlogPost
     */
    public function set_blog_id($value)
    {
        $this->blog_id = $value;
        return $this;
    }

    /**
     * Get blog_id
     *
     * @return integer 
     */
    public function get_blog_id()
    {
        return $this->blog_id;
    }

    /**
     * Set author_id
     *
     * @param integer $value
     * @return BlogPost
     */
    public function set_author_id($value)
    {
        $this->author_id = $value;
        return $this;
    }

    /**
     * Get author_id
     *
     * @return integer 
     */
    public function get_author_id()
    {
        return $this->author_id;
    }
}